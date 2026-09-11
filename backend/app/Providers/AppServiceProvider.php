<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureAuthorization();
    }

    /**
     * Guarda de integridad en desarrollo y tests: es preferible fallar
     * ruidosamente a descartar atributos en silencio por un `fillable`
     * incompleto.
     *
     * No se activa preventAccessingMissingAttributes: una columna nula que no
     * viene en el SELECT (por ejemplo tras un createToken) es legitima, no un
     * error.
     */
    private function configureModels(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Model::preventSilentlyDiscardingAttributes();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('api-heavy', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('agent', fn (Request $request) => Limit::perMinute(60)
            ->by($request->bearerToken() !== null
                ? hash('sha256', (string) $request->bearerToken())
                : (string) $request->ip()));

        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->route('token') ?? '') ?: (string) $request->ip()));
    }

    private function configureAuthorization(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->role->canManageUsers());
    }
}
