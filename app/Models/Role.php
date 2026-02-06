<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RoleScope;


class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = ['position', 'scope'];

    protected $casts = [
        'scope' => RoleScope::class,
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }

    public function assignUser(User $user)
    {
        $this->users()->syncWithoutDetaching([$user->id]);
    }

    public function isBranch(): bool
    {
        return $this->scope === RoleScope::BRANCH;
    }

    public function isCompany(): bool
    {
        return $this->scope === RoleScope::COMPANY;
    }

    public function isGlobal(): bool
    {
        return $this->scope === RoleScope::GLOBAL;
    }
}
