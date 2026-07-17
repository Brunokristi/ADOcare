<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleVertexModelIncidentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(public array $context)
    {
    }

    public function handle(): void
    {
        Log::warning('Vertex runtime candidate incident', [
            'pipeline' => 'dekurz',
            'context' => [
                'reason' => $this->context['reason'] ?? null,
                'active_endpoint_id' => $this->context['active_endpoint_id'] ?? null,
                'fallback_endpoint_id' => $this->context['fallback_endpoint_id'] ?? null,
                'status' => $this->context['status'] ?? null,
            ],
        ]);
    }
}
