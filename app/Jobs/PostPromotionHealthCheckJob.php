<?php

namespace App\Jobs;

use App\Enums\VertexTrainingRunStatus;
use App\Models\VertexTrainingRun;
use App\Services\Vertex\VertexAutotrainStateService;
use App\Services\Vertex\VertexInferenceClient;
use App\Services\Vertex\VertexModelPromotionService;
use App\Services\Vertex\VertexTrainingRunNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PostPromotionHealthCheckJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $runId)
    {
    }

    public function handle(
        VertexAutotrainStateService $stateService,
        VertexInferenceClient $inferenceClient,
        VertexModelPromotionService $promotionService,
        VertexTrainingRunNotifier $notifier
    ): void {
        $run = VertexTrainingRun::query()->find($this->runId);

        if (! $run || $run->status !== VertexTrainingRunStatus::Promoted->value) {
            return;
        }

        $state = $stateService->read();
        $location = trim((string) ($state['active_location'] ?? ''));
        $endpointId = trim((string) ($state['active_endpoint_id'] ?? ''));

        if ($location === '' || $endpointId === '') {
            $promotionService->rollback($run, 'Post-promotion health check zlyhal: chýba aktívny endpoint v state.');
            return;
        }

        $smokePrompt = "You are given a structured nursing proposal. Return JSON only in shape {\"sections\":[{\"text\":\"...\"}]}. INPUT JSON:\n{\"diagnosis\":[\"I10\"],\"nurse_diagnosis\":[\"A110\"],\"epicrisis\":\"Stabilizovaný stav\",\"care_plan\":\"Monitoring\",\"mobility\":[\"I\"],\"expected_duration\":\"one_month\",\"procedures\":[{\"code\":\"3439\",\"frequency\":\"daily\"}]}";

        try {
            $response = $inferenceClient->invokeEndpoint($location, $endpointId, $smokePrompt);
            $text = trim((string) data_get($response, 'candidates.0.content.parts.0.text', ''));
            if ($text === '') {
                $text = trim((string) data_get($response, 'predictions.0.content.parts.0.text', ''));
            }

            $decoded = json_decode($text, true);
            $sections = data_get($decoded, 'sections', []);

            $valid = is_array($decoded)
                && is_array($sections)
                && ! empty($sections)
                && collect($sections)->every(fn ($s) => is_array($s) && trim((string) ($s['text'] ?? '')) !== '')
                && ! str_contains($text, '```');

            if (! $valid) {
                $promotionService->rollback($run, 'Post-promotion health check zlyhal: neplatná odpoveď kandidáta.');
                return;
            }
        } catch (\Throwable $e) {
            $promotionService->rollback($run, 'Post-promotion health check zlyhal: ' . $e->getMessage());
            return;
        }

        $run->completed_at = now();
        $run->save();

        $notifier->notify('health_check_passed', $run);
    }
}
