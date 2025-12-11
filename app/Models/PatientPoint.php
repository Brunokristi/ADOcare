<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientPoint extends Model
{
    use HasFactory;

    protected $table = 'patient_points';

    protected $fillable = [
        'date',
        'patient_personal_number',
        'patient_name',
        'patient_id',
        'diagnosis_code',
        'diagnosis_id',
        'procedure_code',
        'procedure_id',
        'doctor_pzs',
        'doctor_zpr',
        'doctor_id',
        'reference_date',
        'user_id',
        'branch_id',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
        'reference_date' => 'date',
    ];
}
