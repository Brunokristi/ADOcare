<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySubscriptionPaidMonth extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'year',
        'month',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}