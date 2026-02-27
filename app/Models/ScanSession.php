<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'branch_id',
        'user_id',
        'session_token',
        'expires_at',
        'status',
        'document_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the patient for this scan session.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the branch for this scan session.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who initiated this scan session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the document created from this scan session.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Check if session is active (not expired).
     */
    public function isActive(): bool
    {
        return $this->expires_at > now();
    }
}
