<?php

namespace App\Models;

use App\Enums\VertexTrainingRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VertexTrainingRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'pipeline',
        'version',
        'status',
        'idempotency_key',
        'base_model_name',
        'previous_model_name',
        'previous_endpoint_name',
        'previous_endpoint_id',
        'previous_location',
        'training_dataset_uri',
        'validation_dataset_uri',
        'dataset_hash',
        'training_examples_count',
        'validation_examples_count',
        'tuning_job_name',
        'new_model_name',
        'new_endpoint_name',
        'new_endpoint_id',
        'new_location',
        'current_score',
        'candidate_score',
        'json_validity_rate',
        'required_fields_rate',
        'critical_errors',
        'http_failures',
        'average_latency_ms',
        'started_at',
        'completed_at',
        'promoted_at',
        'failed_at',
        'failure_stage',
        'failure_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'promoted_at' => 'datetime',
        'failed_at' => 'datetime',
        'current_score' => 'float',
        'candidate_score' => 'float',
        'json_validity_rate' => 'float',
        'required_fields_rate' => 'float',
        'average_latency_ms' => 'float',
    ];

    public function examples()
    {
        return $this->hasMany(VertexTrainingRunExample::class, 'training_run_id');
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, VertexTrainingRunStatus::activeStatuses(), true);
    }
}
