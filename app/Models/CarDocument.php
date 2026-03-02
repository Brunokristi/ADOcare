<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'mime_type',
        'path',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
