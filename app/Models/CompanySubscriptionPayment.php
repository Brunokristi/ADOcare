<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'received_at',
        'amount',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
        'amount' => 'float',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}