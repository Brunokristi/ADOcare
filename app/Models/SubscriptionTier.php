<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price_monthly',
        'users_limit',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'float',
        'is_active' => 'boolean',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class, 'subscription_tier_id', 'id');
    }
}
