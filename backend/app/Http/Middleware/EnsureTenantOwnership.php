<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Segunda barrera de aislamiento, independiente de las policies.
 *
 * El route model binding se resuelve antes de que corra el middleware de grupo,
 * de modo que un recurso de otro tenant podria llegar ya resuelto al controlador.
 * Aqui se comprueba la pertenencia y se responde 404 (no 403): un tenant no debe
 * poder deducir siquiera la existencia de recursos ajenos
 * (ver docs/security.md, amenaza T8).
 */
class EnsureTenantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $request->route() !== null) {
            foreach ($request->route()->parameters() as $parameter) {
                if (! $parameter instanceof Model) {
                    continue;
                }

                $accountId = $parameter->getAttribute('account_id');

                if ($accountId !== null && (int) $accountId !== (int) $user->account_id) {
                    throw new NotFoundHttpException();
                }
            }
        }

        return $next($request);
    }
}
