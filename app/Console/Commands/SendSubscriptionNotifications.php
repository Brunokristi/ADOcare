<?php

namespace App\Console\Commands;

use App\Mail\GenericEmail;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionNotifications extends Command
{
    protected $signature = 'notifications:send-subscriptions {--dry-run} {--company=} {--to=} {--force}';

    protected $description = 'Send subscription notifications to configured company recipients.';

    /**
     * Calculate subscription end date from the latest paid month.
     */
    private function getSubscriptionEndDate(Company $company): ?\DateTime
    {
        $latestPaidMonth = $company->subscriptionPaidMonths()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        if (!$latestPaidMonth) {
            return null;
        }

        // Last day of the paid month
        return \Carbon\Carbon::create(
            (int) $latestPaidMonth->year,
            (int) $latestPaidMonth->month,
            1
        )->endOfMonth()->toDateTime();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');
        $testRecipient = trim((string) $this->option('to'));
        $force = (bool) $this->option('force');
        $today = now()->startOfDay();
        $expiryOffsets = [30, 10, 5, 1, 0];

        $companies = Company::query()
            ->with(['subscriptionTier', 'subscriptionPaidMonths'])
            ->withCount('users')
            ->where('send_notifications', true)
            ->when($companyId, fn ($query) => $query->whereKey($companyId))
            ->get();

        $sent = 0;

        foreach ($companies as $company) {
            $settings = collect($company->notification_settings ?? []);
            $subscriptionSetting = $settings->first(function ($setting) {
                return is_array($setting)
                    && ($setting['key'] ?? null) === 'subscription'
                    && !empty($setting['enabled']);
            });

            if (!$subscriptionSetting) {
                continue;
            }

            $emails = $testRecipient !== ''
                ? collect([$testRecipient])
                : collect($subscriptionSetting['emails'] ?? [])
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values();

            if ($emails->isEmpty()) {
                $this->warn("Skipping company {$company->id}: no valid notification emails configured.");
                continue;
            }

            $items = [];

            // Calculate subscription end date from latest paid month
            $endsAt = $this->getSubscriptionEndDate($company);
            if ($endsAt) {
                $endsAtCarbon = \Carbon\Carbon::instance($endsAt)->startOfDay();
                $daysUntilEnd = (int) $today->diffInDays($endsAtCarbon, false);

                if ($force || in_array($daysUntilEnd, $expiryOffsets, true)) {
                    $items[] = [
                        'title' => 'Koniec predplatného',
                        'message' => sprintf(
                            'Predplatné končí %s (zostáva %d dní).',
                            $endsAtCarbon->format('d.m.Y'),
                            $daysUntilEnd
                        ),
                    ];
                }
            }

            if (empty($items)) {
                continue;
            }

            $subject = 'Predplatné - ' . ($company->name ?: 'spoločnosť');
            if ($testRecipient !== '') {
                $subject = '[TEST] ' . $subject;
            }

            $viewData = [
                'subject' => $subject,
                'companyName' => $company->name,
                'items' => $items,
            ];

            if ($dryRun) {
                $this->line("[dry-run] Would send {$subject} to: " . $emails->implode(', '));
                $this->line(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $sent++;
                continue;
            }

            Mail::to($emails->all())->send(new GenericEmail($subject, $viewData, 'emails.subscription_notifications'));

            $this->info("Sent subscription notification for company {$company->id} to: " . $emails->implode(', '));
            $sent++;
        }

        $this->info("Completed. Companies notified: {$sent}");

        return self::SUCCESS;
    }
}
