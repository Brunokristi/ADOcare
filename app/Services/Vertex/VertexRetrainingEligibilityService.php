<?php

namespace App\Services\Vertex;

use App\Enums\VertexTrainingRunStatus;
use App\Models\VertexTrainingRun;
use App\Models\VertexTrainingRunExample;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class VertexRetrainingEligibilityService
{
    /**
     * @return array<string, mixed>
     */
    public function evaluate(bool $force = false): array
    {
        if (! (bool) config('services.vertex_ai.auto_train.enabled', false)) {
            return $this->skip('disabled', 'Automatické retrénovanie je vypnuté.');
        }

        $staticEndpointId = trim((string) config('services.vertex_ai.dekurz.endpoint_id'));
        if ($staticEndpointId === '') {
            return $this->skip('missing_static_endpoint', 'Chýba statický Dekurz endpoint (VERTEX_DEKURZ_ENDPOINT_ID).');
        }

        if (Schema::hasTable('vertex_training_runs')) {
            $hasActiveRun = VertexTrainingRun::query()
                ->where('pipeline', 'dekurz')
                ->whereIn('status', VertexTrainingRunStatus::activeStatuses())
                ->exists();

            if ($hasActiveRun) {
                return $this->skip('active_run_exists', 'Retrénovanie už prebieha v inom behu.');
            }
        }

        $day = (int) config('services.vertex_ai.auto_train.day', 1);
        if (! $force && (int) now()->day !== $day) {
            return $this->skip('outside_schedule_day', 'Dnes nie je plánovaný mesačný deň retrénovania.');
        }

        $newExampleCount = $this->countEligibleNewExamples();
        $minimum = (int) config('services.vertex_ai.auto_train.min_new_feedback', 25);

        if (! $force && $newExampleCount < $minimum) {
            return $this->skip(
                'insufficient_examples',
                'Nedostatok nových schválených príkladov pre retrénovanie.',
                [
                    'new_examples' => $newExampleCount,
                    'required_examples' => $minimum,
                ]
            );
        }

        return [
            'can_start' => true,
            'reason' => 'eligible',
            'message' => 'Retrénovanie môže byť spustené.',
            'new_examples' => $newExampleCount,
            'required_examples' => $minimum,
            'pipeline' => 'dekurz',
            'started_at' => Carbon::now()->toIso8601String(),
        ];
    }

    private function countEligibleNewExamples(): int
    {
        if (! Schema::hasTable('dekurz_ai_feedback')) {
            return 0;
        }

        $usedFeedbackIds = [];

        if (Schema::hasTable('vertex_training_run_examples')) {
            $usedFeedbackIds = VertexTrainingRunExample::query()
                ->where('dataset_role', 'training')
                ->pluck('feedback_id')
                ->all();
        }

        $source = trim((string) config('services.vertex_ai.auto_train.source', 'proposal_ai_prefill'));

        $query = \App\Models\DekurzAiFeedback::query()
            ->select(['id', 'proposal_document_id', 'final_sections'])
            ->whereNotNull('proposal_document_id');

        if ($source !== '') {
            $query->where('source', $source);
        }

        if (! empty($usedFeedbackIds)) {
            $query->whereNotIn('id', $usedFeedbackIds);
        }

        $count = 0;

        $query->chunkById(500, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                $final = is_array($row->final_sections) ? $row->final_sections : [];

                if (empty($final)) {
                    continue;
                }

                $hasAnyText = collect($final)
                    ->contains(fn ($section) => trim((string) ($section['text'] ?? '')) !== '');

                if ($hasAnyText) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function skip(string $reason, string $message, array $context = []): array
    {
        return [
            'can_start' => false,
            'reason' => $reason,
            'message' => $message,
            ...$context,
        ];
    }
}
