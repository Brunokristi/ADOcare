<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $table = 'doctors';

    protected $fillable = ['first_name', 'last_name', 'title', 'zpr', 'pzs'];

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_doctor', 'doctor_id', 'branch_id');
    }

}
