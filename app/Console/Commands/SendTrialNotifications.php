<?php

namespace App\Console\Commands;

use App\Mail\GenericEmail;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Trial lifecycle reminders (7/3/1/0 days before expiry). The "trial started" email is
 * sent immediately by OnboardingController::startTrial() - this command only handles the
 * day-offset reminders + the "expired" notice, matching the existing subscription
 * notification pattern (offset-matching instead of a separate sent-log table).
 */
class SendTrialNotifications extends Command
{
    protected $signature = 'notifications:send-trial {--dry-run} {--company=} {--to=} {--force}';

    protected $description = 'Send application trial lifecycle reminders (expiring soon / expired).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companyId = $this->option('company');
        $testRecipient = trim((string) $this->option('to'));
        $force = (bool) $this->option('force');
        $today = now()->startOfDay();
        $expiryOffsets = [7, 3, 1, 0];

        $companies = Company::query()
            ->where('subscription_status', 'trial')
            ->whereNotNull('subscription_ends_at')
            ->with('representative')
            ->when($companyId, fn ($query) => $query->whereKey($companyId))
            ->get();

        $sent = 0;

        foreach ($companies as $company) {
            $endsAt = $company->subscription_ends_at?->copy()->startOfDay();
            if (!$endsAt) {
                continue;
            }

            $daysUntilEnd = (int) $today->diffInDays($endsAt, false);

            if (!$force && !in_array($daysUntilEnd, $expiryOffsets, true)) {
                continue;
            }

            $emails = $this->resolveRecipients($company, $testRecipient);

            if ($emails->isEmpty()) {
                $this->warn("Skipping company {$company->id}: no trial notification recipient available.");
                continue;
            }

            $message = $daysUntilEnd > 0
                ? sprintf('Skúšobné obdobie končí %s (zostáva %d dní).', $endsAt->format('d.m.Y'), $daysUntilEnd)
                : 'Skúšobné obdobie dnes končí. Pre pokračovanie si prosím vyberte platený balík.';

            $subject = 'Skúšobné obdobie - ' . ($company->name ?: 'spoločnosť');
            if ($testRecipient !== '') {
                $subject = '[TEST] ' . $subject;
            }

            $viewData = [
                'subject' => $subject,
                'companyName' => $company->name,
                'items' => [[
                    'title' => $daysUntilEnd > 0 ? 'Skúšobné obdobie čoskoro končí' : 'Skúšobné obdobie skončilo',
                    'message' => $message,
                ]],
            ];

            if ($dryRun) {
                $this->line("[dry-run] Would send {$subject} to: " . $emails->implode(', '));
                continue;
            }

            Mail::to($emails->all())->send(new GenericEmail($subject, $viewData, 'emails.subscription_notifications'));

            $this->info("Sent trial notification for company {$company->id} to: " . $emails->implode(', '));
            $sent++;
        }

        $this->info("Completed. Companies notified: {$sent}");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveRecipients(Company $company, string $testRecipient)
    {
        if ($testRecipient !== '') {
            return collect([$testRecipient]);
        }

        $settings = collect($company->notification_settings ?? []);
        $trialSetting = $settings->first(function ($setting) {
            return is_array($setting) && ($setting['key'] ?? null) === 'trial';
        });

        $configured = collect($trialSetting['emails'] ?? [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($configured->isNotEmpty()) {
            return $configured->unique()->values();
        }

        // Fall back to the Company representative/email so trial reminders always reach
        // someone, even before notification_settings has been configured.
        $fallback = $company->representative?->email ?: $company->email;

        return $fallback ? collect([$fallback]) : collect();
    }
}
