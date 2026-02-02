<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'representative_id',
        'code',
        'identificator',
        'address',
        'city',
        'psc',
        'phone',
        'email',
        'latitude',
        'longitude',
        'terrain_start_time',
        'administrative_start_time',
        'representative_id',
        'per_location_time',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_branches', 'branch_id', 'user_id')
            ->withPivot(['working_time']);
    }

    public function reportMonths()
    {
        return $this->hasMany(ReportMonth::class, 'branch_id');
    }

    public function cars()
    {
        return $this->hasMany(Car::class, 'company_id');
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'branch_id');
    }

    public function favourite_doctors()
    {
        return $this->belongsToMany(Doctor::class, 'branch_favourite_doctors', 'branch_id', 'doctor_id');
    }
}
