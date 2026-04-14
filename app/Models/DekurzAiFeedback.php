<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DekurzAiFeedback extends Model
{
    use HasFactory;

    protected $table = 'dekurz_ai_feedback';

    protected $fillable = [
        'document_id',
        'patient_id',
        'user_id',
        'branch_id',
        'proposal_document_id',
        'source',
        'suggested_sections',
        'final_sections',
        'has_user_edits',
        'suggestion_char_count',
        'final_char_count',
    ];

    protected $casts = [
        'suggested_sections' => 'array',
        'final_sections' => 'array',
        'has_user_edits' => 'boolean',
    ];
}
