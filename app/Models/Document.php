<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'user_id',
        'type',
        'mime_type',
        'name',
        'path',
    ];

    /**
     * Get the patient that owns the document.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the user that uploaded the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
