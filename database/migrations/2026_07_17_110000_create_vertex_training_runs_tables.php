<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vertex_training_runs', function (Blueprint $table) {
            $table->id();
            $table->string('pipeline', 64)->index();
            $table->string('version', 64)->nullable()->index();
            $table->string('status', 32)->index();
            $table->string('idempotency_key', 191)->nullable()->unique();

            $table->string('base_model_name', 512)->nullable();
            $table->string('previous_model_name', 512)->nullable();
            $table->string('previous_endpoint_name', 512)->nullable();
            $table->string('previous_endpoint_id', 128)->nullable();
            $table->string('previous_location', 128)->nullable();

            $table->string('training_dataset_uri', 1024)->nullable();
            $table->string('validation_dataset_uri', 1024)->nullable();
            $table->string('dataset_hash', 128)->nullable()->index();
            $table->unsignedInteger('training_examples_count')->default(0);
            $table->unsignedInteger('validation_examples_count')->default(0);

            $table->string('tuning_job_name', 512)->nullable()->index();
            $table->string('new_model_name', 512)->nullable();
            $table->string('new_endpoint_name', 512)->nullable();
            $table->string('new_endpoint_id', 128)->nullable();
            $table->string('new_location', 128)->nullable();

            $table->decimal('current_score', 8, 4)->nullable();
            $table->decimal('candidate_score', 8, 4)->nullable();
            $table->decimal('json_validity_rate', 8, 4)->nullable();
            $table->decimal('required_fields_rate', 8, 4)->nullable();
            $table->unsignedInteger('critical_errors')->default(0);
            $table->unsignedInteger('http_failures')->default(0);
            $table->unsignedInteger('average_latency_ms')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_stage', 128)->nullable();
            $table->text('failure_message')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('vertex_training_run_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_run_id')->constrained('vertex_training_runs')->cascadeOnDelete();
            $table->foreignId('feedback_id')->constrained('dekurz_ai_feedback')->cascadeOnDelete();
            $table->string('dataset_role', 24)->index();
            $table->timestamp('created_at')->nullable();

            $table->unique(['training_run_id', 'feedback_id', 'dataset_role'], 'vertex_run_examples_run_feedback_role_unique');
            $table->index(['training_run_id', 'dataset_role'], 'vertex_run_examples_run_role_index');
            $table->index(['feedback_id', 'dataset_role'], 'vertex_run_examples_feedback_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertex_training_run_examples');
        Schema::dropIfExists('vertex_training_runs');
    }
};
