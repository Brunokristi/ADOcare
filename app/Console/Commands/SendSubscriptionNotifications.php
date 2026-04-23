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

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');
        $testRecipient = trim((string) $this->option('to'));
        $force = (bool) $this->option('force');
        $today = now()->startOfDay();
        $expiryOffsets = [30, 10, 5, 1, 0];

        $companies = Company::query()
            ->with(['subscriptionTier'])
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

            if ($company->subscription_ends_at) {
                $endsAt = $company->subscription_ends_at->copy()->startOfDay();
                $daysUntilEnd = (int) $today->diffInDays($endsAt, false);

                if ($force || in_array($daysUntilEnd, $expiryOffsets, true)) {
                    $items[] = [
                        'title' => 'Koniec predplatného',
                        'message' => sprintf(
                            'Predplatné končí %s (zostáva %d dní).',
                            $endsAt->format('d.m.Y'),
                            $daysUntilEnd
                        ),
                    ];
                }
            }

            $effectiveLimit = $company->subscription_users_limit_override
                ?? $company->subscriptionTier?->users_limit;
            $usersCount = (int) ($company->users_count ?? 0);

            if ($effectiveLimit !== null) {
                $remaining = (int) $effectiveLimit - $usersCount;

                if ($force || in_array($remaining, [2, 1, 0], true) || $remaining < 0) {
                    $items[] = [
                        'title' => 'Limit používateľov',
                        'message' => sprintf(
                            'Spoločnosť má %d používateľov. Limit je %d. Zostáva %d miest.',
                            $usersCount,
                            (int) $effectiveLimit,
                            $remaining
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
