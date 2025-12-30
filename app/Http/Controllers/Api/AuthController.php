<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use \App\Http\Responses\ApiResponse;


    /**
     * Create a new token for the user.
     * @param \App\Models\User $user
     * @param \DateTimeInterface|null $expiresAt // Default in 1 day
     * @return string
     */
    private function createToken(User $user, $expiresAt = new \DateTime('+1 day'))
    {
        return $user->createToken('AuthToken', [], $expiresAt)->plainTextToken;
    }

    // User Registration API - Only admin can register users
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'login' => $data['login'],
            'pin' => Hash::make($data['pin']),
        ]);

        return $this->success($user, 'User registered successfully.', 201);
    }

    // User Login API
    public function login(LoginRequest $request)
{
    $data = $request->validated();

    $user = User::where('code', $data['login'])
        ->orWhere('login', $data['login'])
        ->first();

    if (!$user || !Hash::check($data['pin'], $user->pin)) {
        return $this->error('Invalid login/code or pin.', 401);
    }

    // Load relationships needed by frontend
    $user->load(['branches', 'company', 'lastBranch']);

    // If no last_branch yet, pick first available branch and save it
        if (!$user->last_branch) {
            $firstBranchId = $user->branches->first()?->id;
            if ($firstBranchId) {
                $user->last_branch = $firstBranchId;
                $user->save();
                $user->load('lastBranch');
            }
        } else {
            // If last_branch is set but user no longer has access, fix it
            $hasAccess = $user->branches->contains('id', $user->last_branch);
            if (!$hasAccess) {
                $fallbackId = $user->branches->first()?->id;
                $user->last_branch = $fallbackId;
                $user->save();
                $user->load('lastBranch');
            }
        }

        $user->roles_list = $user->rolesStringList();

        $token = $this->createToken($user);

        return $this->success([
            'token' => $token,
            'user' => $user,
            'last_branch' => $user->last_branch, // handy for FE
        ], 'Login successful.');
    }

    public function updateLastBranch(Request $request)
    {
        $data = $request->validate([
            'last_branch_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        $allowed = $user->branches()
            ->where('branches.id', $data['last_branch_id'])
            ->exists();

        if (!$allowed) {
            return $this->error('Branch not allowed for this user.', 403);
        }

        $user->last_branch = $data['last_branch_id'];
        $user->save();

        $user->load(['branches', 'company', 'lastBranch']);
        $user->roles_list = $user->rolesStringList();

        return $this->success($user, 'Last branch updated');
    }


    // User Profile API (Protected)
    public function profile(Request $request)
    {
        $userId = auth()->id();

        $user = User::query()
            ->where('id', $userId)
            ->with(['branches', 'company', 'lastBranch'])
            ->first();

        $user->roles_list = $user->rolesStringList();

        return $this->success($user, 'Profile retrieved');
    }


    // User Logout API
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logout successful');
    }
}
