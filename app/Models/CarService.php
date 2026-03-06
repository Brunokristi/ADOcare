<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarService extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'name',
        'date',
        'interval_days',
        'active',
    ];

    protected $casts = [
        'date' => 'date',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Check if service is due this month
     */
    public function isDueThisMonth(): bool
    {
        if (!$this->active || !$this->date) {
            return false;
        }

        $today = now();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        // Calculate the next due date by adding the interval to the last service date
        $nextDueDate = $this->date->copy()->addDays($this->interval_days);

        // Check if the next due date is in the current month
        return $nextDueDate->between($monthStart, $monthEnd);
    }
}
