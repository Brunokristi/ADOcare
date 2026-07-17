<?php

namespace App\Jobs;

use App\Enums\VertexTrainingRunStatus;
use App\Models\VertexTrainingRun;
use App\Services\Vertex\VertexTrainingRunNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class StartMonthlyVertexRetrainingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $eligibilityContext
     */
    public function __construct(
        public array $eligibilityContext,
        public bool $force = false
    ) {
    }

    public function handle(VertexTrainingRunNotifier $notifier): void
    {
        $lock = Cache::lock('vertex-retraining:dekurz', 172800);

        if (! $lock->get()) {
            return;
        }

        try {
            $hasActiveRun = VertexTrainingRun::query()
                ->where('pipeline', 'dekurz')
                ->whereIn('status', VertexTrainingRunStatus::activeStatuses())
                ->exists();

            if ($hasActiveRun) {
                return;
            }

            $run = VertexTrainingRun::query()->create([
                'pipeline' => 'dekurz',
                'version' => now()->format('Y-m-d') . '-001',
                'status' => VertexTrainingRunStatus::Pending->value,
                'started_at' => now(),
                'metadata' => [
                    'trigger' => 'monthly_command',
                    'force' => $this->force,
                    'eligibility' => $this->eligibilityContext,
                ],
            ]);

            $notifier->notify('run_started', $run, [
                'message' => 'Mesačný retraining bol spustený.',
            ]);

            BuildVertexTrainingDatasetJob::dispatch($run->id, $this->force);
        } finally {
            optional($lock)->release();
        }
    }
}
