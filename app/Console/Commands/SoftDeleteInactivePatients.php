<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;
use Carbon\Carbon;

/**
 * Soft-delete patients whose latest patient_points entry is older than a configured cutoff.
 *
 * Usage: php artisan patients:soft-delete-inactive [--months=2] [--dry-run]
 */
class SoftDeleteInactivePatients extends Command
{
    protected $signature = 'patients:soft-delete-inactive {--months=2} {--dry-run}';

    protected $description = 'Soft-delete patients with no recent patient points (older than N months).';

    public function handle()
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = Carbon::now()->startOfDay()->subMonths($months);

        $this->info("Looking for patients whose last patient_points entry is before {$cutoff->toDateString()}...");

        // Find patient_ids where their most recent patient_point.date < cutoff
        $patientIds = DB::table('patient_points')
            ->select('patient_id', DB::raw('MAX(date) as last_date'))
            ->where('deleted_at', null)
            ->groupBy('patient_id')
            ->havingRaw('MAX(date) < ?', [$cutoff->toDateString()])
            ->pluck('patient_id')
            ->filter()
            ->unique()
            ->values();

        $count = $patientIds->count();
        if ($count === 0) {
            $this->info('No inactive patients found.');
            return 0;
        }

        $this->info("Found {$count} patient(s) to consider for soft-deletion.");

        if ($dryRun) {
            // List a sample of patient IDs
            $this->line('Dry run mode — no deletions will be performed.');
            $this->line('Patient IDs: ' . $patientIds->implode(', '));
            return 0;
        }

        // Perform deletions in chunks to avoid memory issues
        $deleted = 0;
        Patient::whereIn('id', $patientIds)->chunkById(100, function ($patients) use (&$deleted) {
            foreach ($patients as $patient) {
                try {
                    $patient->delete();
                    $deleted++;
                } catch (\Exception $e) {
                    // Log and continue
                    report($e);
                }
            }
        });

        $this->info("Soft-deleted {$deleted} patient(s).");
        return 0;
    }
}
