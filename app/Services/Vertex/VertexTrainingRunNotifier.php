<?php

namespace App\Services\Vertex;

use App\Models\VertexTrainingRun;
use App\Notifications\VertexTrainingRunStatusNotification;
use Illuminate\Support\Facades\Notification;

class VertexTrainingRunNotifier
{
    /**
     * @param array<string, mixed> $context
     */
    public function notify(string $event, VertexTrainingRun $run, array $context = []): void
    {
        $emails = config('services.vertex_ai.auto_train.notification_emails', []);

        if (is_string($emails)) {
            $emails = array_values(array_filter(array_map('trim', explode(',', $emails))));
        }

        if (! is_array($emails) || empty($emails)) {
            return;
        }

        $context = array_merge([
            'run_id' => $run->id,
            'dataset_version' => (string) $run->version,
            'new_examples' => (int) ($run->training_examples_count ?? 0),
            'tuning_job_name' => (string) ($run->tuning_job_name ?? ''),
            'previous_endpoint' => (string) ($run->previous_endpoint_id ?? ''),
            'candidate_endpoint' => (string) ($run->new_endpoint_id ?? ''),
            'status' => (string) $run->status,
            'candidate_score' => (string) ($run->candidate_score ?? ''),
            'current_score' => (string) ($run->current_score ?? ''),
            'failure_stage' => (string) ($run->failure_stage ?? ''),
            'message' => (string) ($run->failure_message ?? ''),
        ], $context);

        Notification::route('mail', $emails)
            ->notify(new VertexTrainingRunStatusNotification($event, $context));
    }
}
