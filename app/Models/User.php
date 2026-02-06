<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;

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
        'company_id',
        'initials',
        'login',
        'code',
        'pin',
        'role_id',
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

    /**
     * Custom interface overrides for the model typer.
     *
     * This allows model:typer to emit a precise type for computed attributes / mutators.
     *
     * @var array<string, array<string, mixed>>
     */
    public $interfaces = [
        'role_names' => [
            'type' => 'string[]',
            'nullable' => false,
        ],
    ];


    // Relations
    public function cars()
    {
        return $this->hasMany(Car::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id')
            ->withPivot(['working_time', 'role_id']);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function hasRole($roleName): bool
    {
        return $this->roles()->where('position', $roleName)->exists();
    }


    /**
     * Summary of roleNames
     * @return Attribute<string, array<string>>
     */
    protected function roleNames(): Attribute
    {
        return Attribute::make(

            get: fn() => $this->roleNamesArray()
        );
    }

    /**
     * @return array<string>
     */
    public function roleNamesArray(): array
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

    public function roleInBranch(Branch|int $branch)
    {
        $branchId = $branch instanceof Branch ? $branch->id : (int) $branch;
        $row = DB::table('user_branches')->where('user_id', $this->id)->where('branch_id', $branchId)->first();
        return $row?->role_id ?? null;
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'patient_branch_user')
            ->withPivot('branch_id');
    }

    public function representedCompanies()
    {
        return $this->hasMany(Company::class, 'representative_id');
    }

}
