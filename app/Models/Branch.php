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
        'code',
        'identificator',
        'address',
        'city',
        'psc',
        'phone',
        'email',
        'latitude',
        'longitude',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_branches', 'branch_id', 'user_id');
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
        return $this->belongsToMany(Patient::class, 'patient_branch_users', 'branch_id', 'patient_id');
    }

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class, 'branch_doctor', 'branch_id', 'doctor_id');
    }
}
