<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'company_id', 'code', 'identificator', 'address', 'city', 'psc', 'phone', 'email', 'latitude', 'longitude',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
