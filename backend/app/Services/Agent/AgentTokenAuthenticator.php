<?php

namespace App\Services\Agent;

use App\Domain\Enums\TokenAbility;
use App\Models\ApiToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Autentica peticiones de agentes y webhooks mediante token propio.
 *
 * Compara por hash, verifica revocacion y expiracion, y exige la capacidad
 * necesaria. Nunca registra el token.
 */
class AgentTokenAuthenticator
{
    /**
     * @throws AuthenticationException
     */
    public function authenticate(Request $request, TokenAbility $ability = TokenAbility::EvidenceWrite): ApiToken
    {
        $plain = $request->bearerToken();

        if (blank($plain)) {
            throw new AuthenticationException('Falta el token de agente.');
        }

        $token = ApiToken::findByPlainText($plain);

        if ($token === null) {
            throw new AuthenticationException('Token de agente inválido.');
        }

        if (! $token->isUsable()) {
            throw new AuthenticationException('El token está revocado o vencido.');
        }

        if (! $token->hasAbility($ability)) {
            throw new AuthenticationException('El token no tiene la capacidad requerida.');
        }

        $token->registerUsage($request->ip());

        return $token;
    }
}
