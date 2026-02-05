<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        return $this->success(new UserResource($user), 'User registered successfully.', 201);
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
        $user->load(['branches', 'companies', 'roles']);

        $token = $this->createToken($user);

        return $this->success([
            'token' => $token,
            'user' => $user,
        ], 'Login successful.');
    }


    // User Profile API (Protected)
    public function profile(Request $request)
    {
        $userId = auth()->id();

        $user = User::query()
            ->where('id', $userId)
            ->with(['branches', 'companies', 'roles'])
            ->first();

        return $this->success(new UserResource($user), 'Profile retrieved');
    }


    // User Logout API
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logout successful');
    }
}
