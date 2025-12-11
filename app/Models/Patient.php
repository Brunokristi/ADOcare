<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $fillable = ['first_name', 'last_name', 'title', 'personal_number', 'sex', 'contact', 'doctor_id', 'insurance_company_id', 'address', 'city', 'zip', 'latitude', 'longitude', 'reference_date'];

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'patient_branch_users')
                    ->withPivot('branch_id'); 
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'patient_branch_users')
                    ->withPivot('user_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }
}
