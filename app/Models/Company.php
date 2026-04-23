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
    ];

    protected $casts = [
        'send_notifications' => 'boolean',
        'notification_settings' => 'array',
        'visit_locations' => 'array',
    ];

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
        return $this->hasManyThrough(User::class, Branch::class, 'company_id', 'id', 'id', 'id');
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_id');
    }
}
