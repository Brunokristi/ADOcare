<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'title',
        'phone_number',
        'initials',
        'login',
        'code',
        'pin',
        'last_branch',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'pin',
        'remember_token',
        'api_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'pin' => 'hashed',
    ];



    // Relations
    public function cars()
    {
        return $this->hasMany(Car::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id');
    }

    public function company()
    {
        return $this->hasOneThrough(Company::class, Branch::class, 'id', 'id', 'id', 'company_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function rolesStringList()
    {
        return $this->roles()->pluck('position')->toArray();
    }

    public function reportMonths()
    {
        return $this->hasMany(ReportMonth::class);
    }

    public function assignRole($role)
    {
        if ($role instanceof Role) {
            $roleId = $role->id;
        } else {
            $roleId = (int) $role;
        }
        $this->roles()->syncWithoutDetaching([$roleId]);
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'patient_branch_user')
                    ->withPivot('branch_id');
    }

    public function lastBranch()
    {
        return $this->belongsTo(\App\Models\Branch::class, 'last_branch_id');
    }

    public function representedCompanies()
    {
        return $this->hasMany(Company::class, 'representative_id');
    }

}
