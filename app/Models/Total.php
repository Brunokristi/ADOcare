<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Total extends Model
{
    protected $fillable = [
        'user_id',
        'month',
        'branch_id',
        'insurance_company_id',
        'points_total',
        'kilometers_total',
        'price_paid',
    ];

    protected $casts = [
        'points_total' => 'decimal:2',
        'kilometers_total' => 'decimal:2',
        'price_paid' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
