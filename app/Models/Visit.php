<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $table = 'visits';

    protected $fillable = [
        'date',
        'patient_id',
        'user_id',
        'branch_id',
        'terrain_time',
        'administrative_time',
        'time_on_location',
        'distance_to_location',
        'time_to_location',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'terrain_time' => 'datetime:Y-m-d H:i:s',
        'administrative_time' => 'datetime:Y-m-d H:i:s',
        'time_on_location' => 'integer',
        'distance_to_location' => 'integer',
        'time_to_location' => 'integer',
        'patient_id' => 'integer',
        'user_id' => 'integer',
        'branch_id' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
