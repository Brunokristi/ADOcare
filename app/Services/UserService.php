<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new user and attach branch assignments if provided.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $branches = $data['branches'] ?? null;
        unset($data['branches']);

        $user = User::create($data);

        if ($branches && is_array($branches)) {
            $sync = [];
            foreach ($branches as $b) {
                if (empty($b['branch_id']))
                    continue;
                $sync[(int) $b['branch_id']] = [
                    // use existing pivot column 'working_time' if provided
                    'working_time' => $b['working_time'] ?? null,
                    'role_id' => $b['role_id'] ?? null,
                ];
            }
            if (!empty($sync))
                $user->branches()->sync($sync);
        }

        return $user->fresh()->load(['branches', 'roles']);
    }

    /**
     * Update an existing user and sync branch assignments if provided.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $branches = $data['branches'] ?? null;
        unset($data['branches']);

        $user->update($data);

        if ($branches && is_array($branches)) {
            $sync = [];
            foreach ($branches as $b) {
                if (empty($b['branch_id']))
                    continue;
                $sync[(int) $b['branch_id']] = [
                    'working_time' => $b['working_time'] ?? null,
                    'role_id' => $b['role_id'] ?? null,
                ];
            }
            $user->branches()->sync($sync);
        }

        return $user->fresh()->load(['branches', 'roles']);
    }
}
