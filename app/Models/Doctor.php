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

}
