<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company';

    protected $fillable = [
        'name',
        'ico',
        'dic',
        'ic_dph',
        'iban',
        'bic',
        'register',
        'address',
        'city',
        'psc',
        'phone',
        'email',
        'invoice_number',
        'latitude',
        'longitude',
        'representative_id',
        'stamp_path',
        'send_notifications',
        'notification_settings',
        'visit_locations',
        'subscription_tier_id',
        'subscription_price_monthly',
        'subscription_users_limit_override',
        'subscription_status',
        'subscription_started_at',
        'subscription_ends_at',
        'subscription_notes',
    ];

    protected $casts = [
        'send_notifications' => 'boolean',
        'notification_settings' => 'array',
        'visit_locations' => 'array',
        'subscription_price_monthly' => 'float',
        'subscription_started_at' => 'date',
        'subscription_ends_at' => 'date',
    ];

    public function subscriptionTier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id', 'id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }

    public function patients()
    {
        return $this->hasManyThrough(Patient::class, Branch::class, 'company_id', 'id', 'id', 'id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(CompanySubscriptionPayment::class, 'company_id');
    }

    public function subscriptionPaidMonths()
    {
        return $this->hasMany(CompanySubscriptionPaidMonth::class, 'company_id');
    }
}
