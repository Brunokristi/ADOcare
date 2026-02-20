<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors';

    protected $fillable = ['id', 'first_name', 'last_name', 'title', 'zpr', 'pzs'];

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function favourite_in_branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_favourite_doctors', 'doctor_id', 'branch_id');
    }

    public function assigned_patients()
    {
        return $this->hasMany(Patient::class, 'doctor_id')->whereNull('deleted_at');
    }

    public function assigned_branches()
    {
        return $this->belongsToMany(Branch::class, 'patients', 'doctor_id', 'branch_id')->distinct();
    }

}
