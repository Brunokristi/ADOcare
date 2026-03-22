<?php

namespace App\Services;

use App\Models\Macro;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Encapsulates macro ownership rules and persistence operations.
 */
class MacroService
{
    /**
     * Build the macros query for the given user.
     */
    public function queryForUser(User $user): Builder
    {
        $query = Macro::query();

        if (!$user->hasGlobalRole('superadmin')) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * Create a new macro owned by the given user.
     */
    public function createForUser(array $data, User $user): Macro
    {
        return Macro::create(array_merge($data, ['user_id' => $user->id]));
    }

    /**
     * Check whether a user can access a macro.
     */
    public function canAccess(User $user, Macro $macro): bool
    {
        if ($user->hasGlobalRole('superadmin')) {
            return true;
        }

        return (int) $macro->user_id === (int) $user->id;
    }

    /**
     * Update a macro with validated values.
     */
    public function updateMacro(Macro $macro, array $data): Macro
    {
        $macro->update($data);

        return $macro;
    }

    /**
     * Delete a single macro.
     */
    public function deleteMacro(Macro $macro): void
    {
        $macro->delete();
    }

    /**
     * Delete many macros accessible by the given user.
     *
     * @param array<int> $ids
     */
    public function deleteManyForUser(User $user, array $ids): int
    {
        $query = Macro::whereIn('id', $ids);

        if (!$user->hasGlobalRole('superadmin')) {
            $query->where('user_id', $user->id);
        }

        return $query->delete();
    }
}
