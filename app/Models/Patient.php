<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $fillable = ['first_name', 'last_name', 'title', 'personal_number', 'sex', 'contact', 'doctor_id', 'insurance_company_id', 'address', 'city', 'zip', 'latitude', 'longitude'];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
