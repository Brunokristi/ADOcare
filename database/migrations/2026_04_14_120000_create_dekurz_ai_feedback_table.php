<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dekurz_ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('proposal_document_id')->nullable()->constrained('documents')->onDelete('set null');

            $table->string('source', 64)->default('proposal_ai_prefill');
            $table->json('suggested_sections');
            $table->json('final_sections');
            $table->boolean('has_user_edits')->default(false);
            $table->unsignedInteger('suggestion_char_count')->default(0);
            $table->unsignedInteger('final_char_count')->default(0);
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dekurz_ai_feedback');
    }
};
