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
        'signature_path',
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
        'branch_roles' => [
            'type' => 'Array<{ branch_id: int, role_id: ?int, position: ?string }>',
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

    // roleNames/roleNamesArray removed; use $user->role->position directly

    public function reportMonths()
    {
        return $this->hasMany(ReportMonth::class);
    }

    /**
     * Assign a global role to the user by setting `role_id`.
     *
     * @param Role|string|int $role
     * @return void
     */
    public function assignRole($role)
    {
        $roleId = null;
        if ($role instanceof Role) {
            $roleId = $role->id;
        } elseif (is_string($role)) {
            $roleId = Role::where('position', $role)->value('id');
            if (!$roleId) {
                throw new \InvalidArgumentException("Role with position '{$role}' not found.");
            }
        } elseif (is_int($role)) {
            $roleId = $role;
        } else {
            throw new \InvalidArgumentException("Invalid role type. Expected Role instance, string, or integer.");
        }

        $this->role_id = $roleId;
        $this->save();
    }

    public function roleInBranch(Branch|int $branch)
    {
        $branchId = $branch instanceof Branch ? $branch->id : (int) $branch;
        $row = DB::table('user_branches')->where('user_id', $this->id)->where('branch_id', $branchId)->first();
        return $row?->role_id ?? null;
    }

    /**
     * Check if user belongs to given company.
     */
    public function isInCompany(int $companyId): bool
    {
        return $this->company_id !== null && (int) $this->company_id === $companyId;
    }

    /**
     * Check if user is assigned to the given branch.
     */
    public function isInBranch(int $branchId): bool
    {
        return $this->branches()->where('branch_id', $branchId)->exists();
    }

    /**
     * Check whether the user has a specific role on a branch (pivot.role_id -> roles.position).
     */
    public function hasBranchRole(int $branchId, string $rolePosition): bool
    {
        $roleId = DB::table('user_branches')
            ->where('user_id', $this->id)
            ->where('branch_id', $branchId)
            ->value('role_id');

        if (!$roleId)
            return false;

        return DB::table('roles')->where('id', $roleId)->where('position', $rolePosition)->exists();
    }

    /**
     * Check the user's global/system role.  this does **not** inspect
     * branch-scoped assignments.
     */
    public function hasGlobalRole(string $rolePosition): bool
    {
        // we prefer to use the loaded relationship if available, but
        // fall back to a simple comparison otherwise.
        if ($this->relationLoaded('role')) {
            return $this->role?->position === $rolePosition;
        }
        return $this->role_id !== null &&
            optional(Role::find($this->role_id))->position === $rolePosition;
    }

    /**
     * Computed map of branch_id => role info for branch-scoped roles.
     * This reads the pivot `role_id` from the loaded `branches` relation and
     * resolves the role position when available.
     *
     * @return array<string, mixed>
     */
    protected function branchRoles(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->computeBranchUserRoles()
        );
    }

    /**
     * Build a mapping of branch_id => ['branch_id' => int, 'role_id' => ?int, 'role' => ?array]
     *
     * @return array<int, array<string,mixed>>
     */
    public function computeBranchUserRoles(): array
    {
        // ensure branches are loaded to avoid extra queries in most callers
        $branches = $this->relationLoaded('branches') ? $this->branches : $this->branches()->get();

        $roleIds = collect($branches)->pluck('pivot.role_id')->filter()->unique()->values()->all();
        $roles = [];
        if (!empty($roleIds)) {
            $roles = Role::whereIn('id', $roleIds)->get()->keyBy('id');
        }

        $map = [];
        foreach ($branches as $branch) {
            $roleId = $branch->pivot->role_id ?? null;
            $role = $roleId ? ($roles->get($roleId) ?? null) : null;
            $map[$branch->id] = [
                'branch_id' => $branch->id,
                'role_id' => $roleId,
                'position' => $role?->position ?? null,
            ];
        }

        return $map;
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
