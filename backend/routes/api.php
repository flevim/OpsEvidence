<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AgentEvidenceController;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupWebhookController;
use App\Http\Controllers\Api\CheckController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientSummaryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RuleSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restricciones de parametros
|--------------------------------------------------------------------------
| Los identificadores son numericos. Sin esto, un id no numerico llegaria al
| binding de Eloquent y PostgreSQL lanzaria un error de tipo (500) en lugar de
| un 404.
*/

foreach (['client', 'asset', 'check', 'incident', 'report', 'activity', 'integration', 'api_token', 'environment', 'ruleSetting'] as $parameter) {
    Route::pattern($parameter, '[0-9]+');
}

/*
|--------------------------------------------------------------------------
| Rutas publicas
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class)->name('health');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('auth.login');

/*
|--------------------------------------------------------------------------
| Sesion
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
});

/*
|--------------------------------------------------------------------------
| Ingesta desde agentes y webhooks
|
| Autenticacion por token propio (ApiToken), nunca por sesion de usuario.
|--------------------------------------------------------------------------
*/

Route::post('/agent/evidence', [AgentEvidenceController::class, 'store'])
    ->middleware('throttle:agent')
    ->name('agent.evidence');

Route::post('/webhooks/backup/{token}', [BackupWebhookController::class, 'store'])
    ->middleware('throttle:webhook')
    ->name('webhooks.backup');

/*
|--------------------------------------------------------------------------
| API autenticada
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'account.context', 'tenant.ownership', 'throttle:api'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/summary', ClientSummaryController::class)->name('clients.summary');

    Route::get('clients/{client}/environments', [EnvironmentController::class, 'index']);
    Route::post('clients/{client}/environments', [EnvironmentController::class, 'store']);
    Route::patch('environments/{environment}', [EnvironmentController::class, 'update']);
    Route::delete('environments/{environment}', [EnvironmentController::class, 'destroy']);

    Route::get('clients/{client}/assets', [AssetController::class, 'indexForClient']);
    Route::post('clients/{client}/assets', [AssetController::class, 'storeForClient']);
    Route::apiResource('assets', AssetController::class)->except(['store']);

    Route::get('check-types', [CheckController::class, 'types']);

    Route::get('assets/{asset}/checks', [CheckController::class, 'indexForAsset']);
    Route::post('assets/{asset}/checks', [CheckController::class, 'storeForAsset']);
    Route::apiResource('checks', CheckController::class)->only(['show', 'update', 'destroy']);
    Route::post('checks/{check}/run', [CheckController::class, 'run'])->name('checks.run');

    Route::get('assets/{asset}/evidence', [EvidenceController::class, 'indexForAsset']);
    Route::get('evidence', [EvidenceController::class, 'index']);
    Route::post('evidence', [EvidenceController::class, 'store']);
    Route::post('evidence/backup', [EvidenceController::class, 'storeBackup'])->name('evidence.backup');

    Route::get('incidents', [IncidentController::class, 'index']);
    Route::get('incidents/{incident}', [IncidentController::class, 'show']);
    Route::patch('incidents/{incident}', [IncidentController::class, 'update']);

    Route::apiResource('activities', ActivityController::class);

    Route::get('reports', [ReportController::class, 'index']);
    Route::post('reports', [ReportController::class, 'store']);
    Route::get('reports/{report}', [ReportController::class, 'show']);
    Route::get('reports/{report}/html', [ReportController::class, 'html'])
        ->middleware('throttle:api-heavy')
        ->name('reports.html');
    Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])
        ->middleware('throttle:api-heavy')
        ->name('reports.pdf');
    Route::post('reports/{report}/send', [ReportController::class, 'send'])
        ->middleware('throttle:api-heavy')
        ->name('reports.send');
    Route::post('reports/{report}/mark-sent', [ReportController::class, 'markSent']);

    Route::apiResource('integrations', IntegrationController::class);

    Route::get('api-tokens', [ApiTokenController::class, 'index']);
    Route::post('api-tokens', [ApiTokenController::class, 'store']);
    Route::delete('api-tokens/{api_token}', [ApiTokenController::class, 'destroy']);

    Route::get('rule-settings', [RuleSettingController::class, 'index']);
    Route::patch('rule-settings/{ruleSetting}', [RuleSettingController::class, 'update']);
});
