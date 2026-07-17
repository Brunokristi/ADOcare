<?php

namespace App\Console\Commands;

use App\Models\VertexTrainingRun;
use Illuminate\Console\Command;

class VertexCleanupCommand extends Command
{
    protected $signature = 'vertex:cleanup';

    protected $description = 'Bezpečne čistí historické metadáta retrénovania podľa retention policy';

    public function handle(): int
    {
        $retentionDays = (int) config('services.vertex_ai.auto_train.retention_days', 180);

        $deleted = VertexTrainingRun::query()
            ->whereIn('status', ['failed', 'skipped', 'evaluation_failed'])
            ->where('updated_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info('Vymazané historické runy: ' . $deleted);

        return self::SUCCESS;
    }
}
