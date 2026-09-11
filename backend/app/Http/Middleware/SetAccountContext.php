<?php

namespace App\Http\Middleware;

use App\Support\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece el tenant activo a partir del usuario autenticado.
 *
 * El account_id NUNCA se toma de la peticion: siempre del usuario autenticado
 * (ver docs/security.md, amenaza T1).
 */
class SetAccountContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            AccountContext::set($user->account_id);
        }

        try {
            return $next($request);
        } finally {
            AccountContext::forget();
        }
    }
}
