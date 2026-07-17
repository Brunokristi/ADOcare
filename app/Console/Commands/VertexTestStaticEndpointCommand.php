<?php

namespace App\Console\Commands;

use App\Services\Vertex\VertexInferenceClient;
use Illuminate\Console\Command;

class VertexTestStaticEndpointCommand extends Command
{
    protected $signature = 'vertex:test-static-endpoint';

    protected $description = 'Otestuje statický produkčný Dekurz endpoint z konfigurácie';

    public function handle(VertexInferenceClient $client): int
    {
        $location = trim((string) config('services.vertex_ai.dekurz.location'));
        $endpointId = trim((string) config('services.vertex_ai.dekurz.endpoint_id'));

        if ($location === '' || $endpointId === '') {
            $this->error('Statický endpoint nie je nakonfigurovaný.');
            return self::FAILURE;
        }

        try {
            $client->invokeEndpoint($location, $endpointId, $this->smokePrompt());
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Statický endpoint odpovedá korektne.');

        return self::SUCCESS;
    }

    private function smokePrompt(): string
    {
        return "You are given a structured nursing proposal. Return JSON only in shape {\"sections\":[{\"text\":\"...\"}]}. INPUT JSON:\n{\"diagnosis\":[\"I10\"],\"nurse_diagnosis\":[\"A110\"],\"epicrisis\":\"Stabilizovaný stav\",\"care_plan\":\"Monitoring\",\"mobility\":[\"I\"],\"expected_duration\":\"one_month\",\"procedures\":[{\"code\":\"3439\",\"frequency\":\"daily\"}]}";
    }
}
