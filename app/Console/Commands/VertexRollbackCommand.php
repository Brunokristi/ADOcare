<?php

namespace App\Console\Commands;

use App\Models\VertexTrainingRun;
use App\Services\Vertex\VertexModelPromotionService;
use Illuminate\Console\Command;

class VertexRollbackCommand extends Command
{
    protected $signature = 'vertex:rollback
        {run_id : ID tréning behu}
        {--reason=manual_rollback : Dôvod rollbacku}';

    protected $description = 'Manuálne vráti aktívny endpoint na predchádzajúci state';

    public function handle(VertexModelPromotionService $promotionService): int
    {
        $run = VertexTrainingRun::query()->find((int) $this->argument('run_id'));

        if (! $run) {
            $this->error('Training run neexistuje.');
            return self::FAILURE;
        }

        $reason = (string) $this->option('reason');

        $promotionService->rollback($run, $reason);

        $this->info('Rollback bol vykonaný.');

        return self::SUCCESS;
    }
}
