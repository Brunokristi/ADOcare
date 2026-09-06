<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterCompanyRequest;
use App\Http\Resources\UserResource;
use App\Services\RegistrationService;

/**
 * Public entry point: creates the initial Manager User + Company and starts onboarding.
 * Distinct from AuthController::register(), which is a superadmin-only internal-staff
 * (PIN/code) account creation endpoint unrelated to Company registration.
 */
class RegistrationController extends Controller
{
    public function __construct(private RegistrationService $registration)
    {
    }

    public function store(RegisterCompanyRequest $request)
    {
        $result = $this->registration->register($request->validated());
        $user = $result['user'];

        $token = $user->createToken('AuthToken', [], now()->addDay())->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Registrácia bola úspešná.', 201);
    }
}
