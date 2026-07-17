<?php

namespace App\Console\Commands;

use App\Jobs\StartMonthlyVertexRetrainingJob;
use App\Services\Vertex\VertexRetrainingEligibilityService;
use Illuminate\Console\Command;

class RunMonthlyVertexRetraining extends Command
{
    protected $signature = 'vertex:monthly-retrain
        {--force : Preskočí mesačný deň a minimálny počet nových príkladov}
        {--dry-run : Vykoná iba kontrolu spustiteľnosti bez spustenia retrénovania}';

    protected $description = 'Mesačne spustí bezpečný retraining pipeline pre Dekurz Vertex model';

    public function __construct(
        private readonly VertexRetrainingEligibilityService $eligibility
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $result = $this->eligibility->evaluate($force);

        if (! (bool) ($result['can_start'] ?? false)) {
            $this->warn((string) ($result['message'] ?? 'Retrénovanie bolo preskočené.'));

            if (isset($result['new_examples'], $result['required_examples'])) {
                $this->line('Nové príklady: ' . $result['new_examples']);
                $this->line('Minimálny počet: ' . $result['required_examples']);
            }

            return self::SUCCESS;
        }

        $this->info((string) ($result['message'] ?? 'Retrénovanie je spustiteľné.'));
        $this->line('Nové príklady: ' . (string) ($result['new_examples'] ?? 0));

        if ($dryRun) {
            $this->info('Dry-run dokončený. Tréning nebol spustený.');
            return self::SUCCESS;
        }

        StartMonthlyVertexRetrainingJob::dispatch($result, $force);

        $this->info('Mesačný retraining job bol odoslaný do fronty.');

        return self::SUCCESS;
    }
}
