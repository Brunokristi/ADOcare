<?php

namespace App\Console\Commands;

use App\Models\VertexTrainingRun;
use App\Services\Vertex\VertexCandidateEvaluationService;
use Illuminate\Console\Command;

class VertexEvaluateCandidateCommand extends Command
{
    protected $signature = 'vertex:evaluate-candidate {run_id : ID tréning behu}';

    protected $description = 'Manuálne vyhodnotí kandidátny endpoint voči stabilnej validačnej sade';

    public function handle(VertexCandidateEvaluationService $evaluationService): int
    {
        $run = VertexTrainingRun::query()->find((int) $this->argument('run_id'));

        if (! $run) {
            $this->error('Training run neexistuje.');
            return self::FAILURE;
        }

        try {
            $evaluation = $evaluationService->evaluate($run);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('Candidate score: ' . number_format((float) ($evaluation['candidate_score'] ?? 0), 4));
        $this->line('Current score: ' . number_format((float) ($evaluation['current_score'] ?? 0), 4));
        $this->line('Passes: ' . (((bool) ($evaluation['passes'] ?? false)) ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
