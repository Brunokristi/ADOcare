<?php

namespace App\Console\Commands;

use App\Models\CarService;
use App\Models\Company;
use App\Mail\GenericEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCarMaintenanceNotifications extends Command
{
    protected $signature = 'notifications:send-car-maintenance {--dry-run} {--company=} {--to=} {--force}';

    protected $description = 'Send car maintenance notifications to configured company email recipients.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');
        $testRecipient = trim((string) $this->option('to'));
        $force = (bool) $this->option('force');
        $today = now()->startOfDay();
        $offsets = [30, 10, 5, 1];

        $companies = Company::query()
            ->where('send_notifications', true)
            ->when($companyId, fn ($query) => $query->whereKey($companyId))
            ->get();
        $sent = 0;

        foreach ($companies as $company) {
            $settings = collect($company->notification_settings ?? []);
            $carMaintenanceSetting = $settings->first(function ($setting) {
                return is_array($setting)
                    && ($setting['key'] ?? null) === 'car_maintenance'
                    && !empty($setting['enabled']);
            });

            if (!$carMaintenanceSetting) {
                continue;
            }

            $emails = $testRecipient !== ''
                ? collect([$testRecipient])
                : collect($carMaintenanceSetting['emails'] ?? [])
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values();

            if ($emails->isEmpty()) {
                $this->warn("Skipping company {$company->id}: no valid notification emails configured.");
                continue;
            }

            $services = CarService::query()
                ->whereHas('car', fn ($query) => $query->where('company_id', $company->id))
                ->where('active', true)
                ->whereNotNull('date')
                ->with(['car.user'])
                ->get()
                ->filter(function (CarService $service) use ($today, $offsets) {
                    if ($this->option('force')) {
                        return true;
                    }

                    $nextDueDate = $this->getNextDueDate($service);

                    if (!$nextDueDate) {
                        return false;
                    }

                    $daysUntil = (int) $today->diffInDays($nextDueDate, false);

                    return in_array($daysUntil, $offsets, true)
                        && $nextDueDate->greaterThanOrEqualTo($today);
                })
                ->values();

            if ($services->isEmpty()) {
                continue;
            }

            $subject = 'Údržba áut - ' . ($company->name ?: 'spoločnosť');
            if ($testRecipient !== '') {
                $subject = '[TEST] ' . $subject;
            }
            $body = $this->buildBody($company, $services, $today);

            if ($dryRun) {
                $this->line("[dry-run] Would send {$subject} to: " . $emails->implode(', '));
                $this->line($body);
                $sent++;
                continue;
            }

            $viewData = [
                'companyName' => $company->name,
                'services' => $services->map(function (CarService $service) use ($today) {
                    $car = $service->car;
                    $driverName = trim(implode(' ', array_filter([
                        $car?->user?->title,
                        $car?->user?->first_name,
                        $car?->user?->last_name,
                    ])));
                    $nextDueDate = $this->getNextDueDate($service);

                    return [
                        'name' => $service->name,
                        'car_model' => $car?->model,
                        'car_evc' => $car?->evc,
                        'driver_name' => $driverName,
                        'next_due_date' => $nextDueDate?->format('d.m.Y') ?? '-',
                        'days_until' => $nextDueDate ? (int) $today->diffInDays($nextDueDate, false) : '-',
                    ];
                })->all(),
            ];

            Mail::to($emails->all())->send(new GenericEmail($subject, $viewData, 'emails.car_maintenance_notifications'));

            $this->info("Sent car maintenance notification for company {$company->id} to: " . $emails->implode(', '));
            $sent++;
        }

        $this->info("Completed. Companies notified: {$sent}");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CarService>  $services
     */
    private function buildBody(Company $company, $services, $today): string
    {
        $lines = [
            'Dobrý deň,',
            '',
            'pre ' . ($company->name ?: 'Vašu spoločnosť') . ' sú naplánované upozornenia na údržbu vozidiel:',
            'Posielame ich 30, 10, 5 a 1 deň pred termínom údržby.',
            '',
        ];

        foreach ($services as $service) {
            $car = $service->car;
            $userName = trim(($car?->user?->title ?? '') . ' ' . ($car?->user?->first_name ?? '') . ' ' . ($car?->user?->last_name ?? ''));
            $nextDueDate = $this->getNextDueDate($service);
            $daysUntil = $nextDueDate ? (int) $today->diffInDays($nextDueDate, false) : null;

            $lines[] = sprintf(
                '- %s | %s (%s) | vodič: %s | ďalší termín: %s | zostáva: %s dní',
                $service->name,
                $car?->model ?? '-',
                $car?->evc ?? '-',
                $userName ?: '-',
                $nextDueDate?->toDateString() ?? '-',
                $daysUntil ?? '-'
            );
        }

        $lines[] = '';
        $lines[] = 'S pozdravom,';
        $lines[] = 'ADOcare';

        return implode("\n", $lines);
    }

    private function getNextDueDate(CarService $service): ?\Illuminate\Support\Carbon
    {
        if (!$service->date) {
            return null;
        }

        return $service->date->copy()->addDays($service->interval_days);
    }
}