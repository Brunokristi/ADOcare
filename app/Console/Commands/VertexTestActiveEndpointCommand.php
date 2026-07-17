<?php

namespace App\Console\Commands;

use App\Services\Vertex\VertexAutotrainStateService;
use App\Services\Vertex\VertexInferenceClient;
use Illuminate\Console\Command;

class VertexTestActiveEndpointCommand extends Command
{
    protected $signature = 'vertex:test-active-endpoint';

    protected $description = 'Otestuje aktívny endpoint zo state.json (fallback na statický)';

    public function handle(VertexAutotrainStateService $stateService, VertexInferenceClient $client): int
    {
        $state = $stateService->read();

        $location = trim((string) ($state['active_location'] ?? config('services.vertex_ai.dekurz.location')));
        $endpointId = trim((string) ($state['active_endpoint_id'] ?? config('services.vertex_ai.dekurz.endpoint_id')));

        if ($location === '' || $endpointId === '') {
            $this->error('Aktívny endpoint nie je dostupný.');
            return self::FAILURE;
        }

        try {
            $client->invokeEndpoint($location, $endpointId, $this->smokePrompt());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Aktívny endpoint odpovedá korektne.');

        return self::SUCCESS;
    }

    private function smokePrompt(): string
    {
        return "You are given a structured nursing proposal. Return JSON only in shape {\"sections\":[{\"text\":\"...\"}]}. INPUT JSON:\n{\"diagnosis\":[\"I10\"],\"nurse_diagnosis\":[\"A110\"],\"epicrisis\":\"Stabilizovaný stav\",\"care_plan\":\"Monitoring\",\"mobility\":[\"I\"],\"expected_duration\":\"one_month\",\"procedures\":[{\"code\":\"3439\",\"frequency\":\"daily\"}]}";
    }
}
