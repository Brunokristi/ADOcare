<?php

namespace App\Console\Commands;

use App\Enums\VertexTrainingRunStatus;
use App\Models\VertexTrainingRun;
use App\Services\Vertex\VertexModelPromotionService;
use Illuminate\Console\Command;

class VertexPromoteCandidateCommand extends Command
{
    protected $signature = 'vertex:promote-candidate
        {run_id : ID tréning behu}
        {--emergency : Núdzová promócia bez status guardu (NEODPORÚČA SA)}';

    protected $description = 'Manuálne povýši validovaného kandidáta do aktívneho state';

    public function handle(VertexModelPromotionService $promotionService): int
    {
        $run = VertexTrainingRun::query()->find((int) $this->argument('run_id'));

        if (! $run) {
            $this->error('Training run neexistuje.');
            return self::FAILURE;
        }

        $isEmergency = (bool) $this->option('emergency');

        if (! $isEmergency && $run->status !== VertexTrainingRunStatus::ReadyForPromotion->value) {
            $this->error('Run nie je v stave ready_for_promotion.');
            return self::FAILURE;
        }

        $deployment = [
            'model_name' => (string) $run->new_model_name,
            'endpoint_name' => (string) $run->new_endpoint_name,
            'endpoint_id' => (string) $run->new_endpoint_id,
            'location' => (string) $run->new_location,
        ];

        $evaluation = is_array($run->metadata) ? ($run->metadata['evaluation'] ?? []) : [];

        try {
            $promotionService->promote($run, $deployment, is_array($evaluation) ? $evaluation : []);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Kandidát bol úspešne povýšený.');

        return self::SUCCESS;
    }
}
