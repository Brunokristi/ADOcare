<?php

namespace Tests\Unit\Vertex;

use App\Models\VertexTrainingRun;
use App\Services\Vertex\DekurzPromptBuilder;
use App\Services\Vertex\VertexAutotrainStateService;
use App\Services\Vertex\VertexCandidateEvaluationService;
use App\Services\Vertex\VertexInferenceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class VertexCandidateEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_evaluation_fails_when_candidate_response_is_malformed_json(): void
    {
        Storage::fake('local');

        config()->set('services.vertex_ai.dekurz.location', 'europe-southwest1');
        config()->set('services.vertex_ai.dekurz.endpoint_id', '4454636175562375168');

        Storage::disk('local')->put('ai/dekurz-autotrain/datasets/validation.jsonl', json_encode([
            'feedback_id' => 1,
            'input_prompt' => 'prompt',
            'expected' => ['sections' => [['text' => 'expected']]],
        ]));

        Storage::disk('local')->put('ai/dekurz-autotrain/datasets/holdout.jsonl', json_encode([
            'feedback_id' => 2,
            'input_prompt' => 'prompt-2',
            'expected' => ['sections' => [['text' => 'expected-2']]],
        ]));

        $stateService = Mockery::mock(VertexAutotrainStateService::class);
        $stateService->shouldReceive('read')->andReturn([
            'active_location' => 'europe-southwest1',
            'active_endpoint_id' => '4454636175562375168',
        ]);

        $client = Mockery::mock(VertexInferenceClient::class);
        $client->shouldReceive('invokeEndpoint')->andReturn([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => 'not-json',
                    ]],
                ],
            ]],
        ]);

        $service = new VertexCandidateEvaluationService(
            app(DekurzPromptBuilder::class),
            $client,
            $stateService
        );

        $run = VertexTrainingRun::query()->create([
            'pipeline' => 'dekurz',
            'version' => '2026-08-01-001',
            'status' => 'evaluating',
            'new_endpoint_id' => 'candidate-endpoint',
            'new_location' => 'europe-southwest1',
        ]);

        $evaluation = $service->evaluate($run);

        $this->assertFalse((bool) $evaluation['passes']);
        $this->assertSame(0.0, (float) data_get($evaluation, 'candidate.valid_json_rate'));
    }
}
