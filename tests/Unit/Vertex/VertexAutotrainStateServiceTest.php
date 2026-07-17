<?php

namespace Tests\Unit\Vertex;

use App\Services\Vertex\VertexAutotrainStateService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VertexAutotrainStateServiceTest extends TestCase
{
    public function test_write_atomic_and_read_state_roundtrip(): void
    {
        Storage::fake('local');

        config()->set('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');

        $service = app(VertexAutotrainStateService::class);

        $state = [
            'schema_version' => 1,
            'pipeline' => 'dekurz',
            'active_endpoint_id' => '4454636175562375168',
            'active_location' => 'europe-southwest1',
        ];

        $service->writeAtomic($state);
        $read = $service->read();

        $this->assertSame('dekurz', $read['pipeline']);
        $this->assertSame('4454636175562375168', $read['active_endpoint_id']);
    }

    public function test_read_returns_empty_array_when_missing_state_file(): void
    {
        Storage::fake('local');

        config()->set('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/missing.json');

        $service = app(VertexAutotrainStateService::class);

        $this->assertSame([], $service->read());
    }
}
