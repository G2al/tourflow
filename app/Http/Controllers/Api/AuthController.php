<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterAgencyRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BookingNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registrazione di una nuova agenzia: crea utente + anagrafica agenzia
     * e restituisce subito il token di accesso.
     */
    public function register(RegisterAgencyRequest $request, BookingNotifier $notifier): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Agency,
            ]);

            $user->agency()->create($data['agency']);

            return $user;
        });

        $notifier->agencyRegistered($user->agency);

        return $this->tokenResponse($user, $data['device_name'] ?? null, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenziali non valide.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Account disabilitato. Contatta il tour operator.']);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('agency'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->update(Arr::only($data, ['name', 'email', 'phone', 'password']));

            if (isset($data['agency']) && $user->agency) {
                $user->agency->update($data['agency']);
            }
        });

        return new UserResource($user->fresh('agency'));
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('agency')),
        ], $status);
    }
}
