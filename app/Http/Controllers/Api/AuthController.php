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

    // User Registration API
    public function register(CodeRegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'pin' => Hash::make($data['pin']),
        ]);

        $token = $user->createToken('MyAppToken')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $user], 'User registered successfully.', 201);
    }

    // User Login API
    public function login(CodeLoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('code', $data['code'])->first();
        if (!$user || !Hash::check($data['pin'], $user->pin)) {
            return $this->error('Unauthorized', 401);
        }

        $token = $user->createToken('MyAppToken')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $user], 'Login successful.');
    }

    // User Profile API (Protected)
    public function profile(Request $request)
    {
        return $this->success($request->user(), 'Profile retrieved');
    }

    // User Logout API
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logout successful');
    }
}
