<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CodeLoginRequest;
use App\Http\Requests\Api\CodeRegisterRequest;
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
    public function register(CodeRegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'pin' => Hash::make($data['pin']),
        ]);

        return $this->success($user, 'User registered successfully.', 201);
    }

    // User Login API
    public function login(CodeLoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('code', $data['code'])->first();

        if (!$user || !Hash::check($data['pin'], $user->pin)) {
            return $this->error('Invalid code or pin.', 401);
        }

        $token = $this->createToken($user);

        return $this->success(['token' => $token, 'user' => $user], 'Login successful.');
    }

    // User Profile API (Protected)
    public function profile(Request $request)
    {
        $userId = auth()->id();
        $user = User::query()->where('id', $userId)->with(['branches', 'company', 'roles'])->first();
        return $this->success($user, 'Profile retrieved');

    }

    // User Logout API
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logout successful');
    }
}
