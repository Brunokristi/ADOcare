<?php

namespace Tests\Unit\Vertex;

use App\Models\VertexTrainingRun;
use App\Notifications\VertexTrainingRunStatusNotification;
use App\Services\Vertex\VertexAutotrainStateService;
use App\Services\Vertex\VertexModelPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VertexModelPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_promote_writes_atomic_state_and_updates_run_status(): void
    {
        Storage::fake('local');
        Notification::fake();

        config()->set('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');
        config()->set('services.vertex_ai.auto_train.notification_emails', ['ops@example.com']);

        $stateService = app(VertexAutotrainStateService::class);
        $stateService->writeAtomic([
            'schema_version' => 1,
            'pipeline' => 'dekurz',
            'active_model_name' => 'projects/x/models/current',
            'active_endpoint_name' => 'projects/x/endpoints/current',
            'active_endpoint_id' => '4454636175562375168',
            'active_location' => 'europe-southwest1',
        ]);

        $run = VertexTrainingRun::query()->create([
            'pipeline' => 'dekurz',
            'version' => '2026-08-01-001',
            'status' => 'ready_for_promotion',
            'dataset_hash' => 'abc',
        ]);

        $service = app(VertexModelPromotionService::class);

        $service->promote($run, [
            'model_name' => 'projects/x/models/new',
            'endpoint_name' => 'projects/x/endpoints/new',
            'endpoint_id' => '9999',
            'location' => 'europe-southwest1',
        ], [
            'candidate_score' => 0.95,
        ]);

        $state = $stateService->read();

        $this->assertSame('9999', $state['active_endpoint_id']);
        $this->assertSame('4454636175562375168', $state['previous_endpoint_id']);

        $run->refresh();
        $this->assertSame('promoted', $run->status);
        $this->assertNotNull($run->promoted_at);

        Notification::assertSentOnDemand(VertexTrainingRunStatusNotification::class);
    }

    public function test_rollback_restores_previous_state(): void
    {
        Storage::fake('local');
        Notification::fake();

        config()->set('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');
        config()->set('services.vertex_ai.auto_train.notification_emails', ['ops@example.com']);

        $stateService = app(VertexAutotrainStateService::class);
        $stateService->writeAtomic([
            'schema_version' => 1,
            'pipeline' => 'dekurz',
            'active_model_name' => 'projects/x/models/new',
            'active_endpoint_name' => 'projects/x/endpoints/new',
            'active_endpoint_id' => '9999',
            'active_location' => 'europe-southwest1',
            'previous_model_name' => 'projects/x/models/current',
            'previous_endpoint_name' => 'projects/x/endpoints/current',
            'previous_endpoint_id' => '4454636175562375168',
            'previous_location' => 'europe-southwest1',
        ]);

        $run = VertexTrainingRun::query()->create([
            'pipeline' => 'dekurz',
            'version' => '2026-08-01-001',
            'status' => 'promoted',
        ]);

        $service = app(VertexModelPromotionService::class);
        $service->rollback($run, 'test rollback');

        $state = $stateService->read();

        $this->assertSame('4454636175562375168', $state['active_endpoint_id']);

        $run->refresh();
        $this->assertSame('rolled_back', $run->status);

        Notification::assertSentOnDemand(VertexTrainingRunStatusNotification::class);
    }
}
