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


}
