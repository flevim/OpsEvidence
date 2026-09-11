<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::withoutGlobalScopes()
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Las credenciales no coinciden con nuestros registros.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('Esta cuenta de usuario está desactivada.'),
            ]);
        }

        if (! $user->account->isActive()) {
            throw ValidationException::withMessages([
                'email' => __('La cuenta de la organización está suspendida.'),
            ]);
        }

        $token = $user->createToken(
            name: 'spa:'.$request->input('device_name', 'web'),
            expiresAt: now()->addDays(14),
        );

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::record(
            event: 'auth.login',
            subject: $user,
            accountId: $user->account_id,
            userId: $user->id,
            ip: $request->ip(),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user->load('account')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        AuditLog::record(
            event: 'auth.logout',
            subject: $request->user(),
            accountId: $request->user()->account_id,
            userId: $request->user()->id,
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('account')),
        ]);
    }
}
