<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\IncidentStatus;
use App\Domain\Enums\RuleKey;
use App\Models\Account;
use App\Models\Client;
use App\Models\Incident;
use App\Services\Rules\IncidentManager;
use App\Services\Rules\RulesEngine;

function evaluateAndSync(Client $client): array
{
    $engine = app(RulesEngine::class);
    $context = $engine->evaluateClient($client);

    return [
        'violations' => $context->violations(),
        'result' => app(IncidentManager::class)->sync($client->account_id, $client->id, $context->violations()),
    ];
}

it('abre un incidente crítico cuando el disco supera el umbral', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Critical, ['value_numeric' => 95.0]);

    ['violations' => $violations, 'result' => $result] = evaluateAndSync($client);

    expect($violations)->not->toBeEmpty()
        ->and($result['opened'])->toBe(1);

    $incident = Incident::withoutGlobalScopes()->firstOrFail();

    expect($incident->rule_key)->toBe(RuleKey::DiskUsage)
        ->and($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->asset_id)->toBe($asset->id)
        ->and($incident->metadata['recommendation'])->not->toBeNull();
});

it('no genera incidentes duplicados al reevaluar la misma condición', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Critical, ['value_numeric' => 95.0]);

    evaluateAndSync($client);
    evaluateAndSync($client);
    evaluateAndSync($client);

    expect(Incident::withoutGlobalScopes()->count())->toBe(1);
});

it('resuelve solo el incidente cuando la condición desaparece', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Critical, [
        'value_numeric' => 95.0,
        'collected_at' => now()->subMinutes(30),
    ]);

    evaluateAndSync($client);
    expect(Incident::withoutGlobalScopes()->where('status', IncidentStatus::Open->value)->count())->toBe(1);

    // Llega evidencia nueva y saludable: el disco ya no está crítico.
    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy, [
        'value_numeric' => 40.0,
        'collected_at' => now()->subMinute(),
    ]);

    evaluateAndSync($client);

    $incident = Incident::withoutGlobalScopes()->firstOrFail();

    expect($incident->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->resolution_note)->toContain('dejó de cumplirse');
});

it('no genera incidentes por debajo del umbral', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy, ['value_numeric' => 42.0]);

    ['violations' => $violations] = evaluateAndSync($client);

    expect($violations)->toBeEmpty()
        ->and(Incident::withoutGlobalScopes()->count())->toBe(0);
});

it('detecta un certificado próximo a vencer', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Website);

    makeEvidence($asset, CheckType::SslExpiration, EvidenceStatus::Warning, [
        'value_numeric' => 12.0,
        'data' => ['host' => 'acme.test', 'days_remaining' => 12],
    ]);

    ['result' => $result] = evaluateAndSync($client);

    expect($result['opened'])->toBe(1)
        ->and(Incident::withoutGlobalScopes()->firstOrFail()->rule_key)->toBe(RuleKey::SslExpiring);
});

it('detecta un backup detenido en el tiempo', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::BackupSource);

    makeEvidence($asset, CheckType::BackupStatus, EvidenceStatus::Healthy, [
        'collected_at' => now()->subDays(5),
    ]);

    evaluateAndSync($client);

    $incident = Incident::withoutGlobalScopes()->firstOrFail();

    expect($incident->rule_key)->toBe(RuleKey::BackupStale)
        ->and($incident->severity->value)->toBe('critical');
});

it('avisa cuando la recolección de un check lleva fallando', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeCheck($asset, CheckType::DiskUsage, [
        'consecutive_failures' => 5,
        'last_error' => 'Connection timed out',
    ]);

    evaluateAndSync($client);

    $incident = Incident::withoutGlobalScopes()->firstOrFail();

    expect($incident->rule_key)->toBe(RuleKey::CollectorFailing)
        ->and($incident->description)->toContain('Connection timed out');
});

it('permite desactivar una regla por cuenta', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Critical, ['value_numeric' => 99.0]);

    \App\Models\RuleSetting::withoutGlobalScopes()->create([
        'account_id' => $account->id,
        'client_id' => null,
        'rule_key' => RuleKey::DiskUsage->value,
        'enabled' => false,
    ]);

    ['violations' => $violations] = evaluateAndSync($client);

    expect($violations)->toBeEmpty();
});

it('genera un informe con score y HTML imprimible', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);
    $asset = makeAsset($client, AssetType::Website);

    makeEvidence($asset, CheckType::HttpStatus, EvidenceStatus::Healthy, ['value_numeric' => 200.0]);
    makeEvidence($asset, CheckType::BackupStatus, EvidenceStatus::Healthy);
    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy, ['value_numeric' => 40.0]);
    makeEvidence($asset, CheckType::SslExpiration, EvidenceStatus::Healthy, ['value_numeric' => 120.0]);

    $response = $this->postJson('/api/reports', [
        'client_id' => $client->id,
        'period_start' => now()->subDays(7)->toDateString(),
        'period_end' => now()->toDateString(),
    ])->assertCreated();

    expect($response->json('health_score'))->not->toBeNull()
        ->and($response->json('summary'))->not->toBeEmpty();

    $html = $this->get('/api/reports/'.$response->json('id').'/html')->assertOk();

    expect($html->getContent())
        ->toContain($client->name)
        ->toContain('Informe de infraestructura')
        ->toContain('Desglose del índice');
});

it('informa «sin datos» en lugar de cero cuando no hay evidencia', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);
    makeAsset($client, AssetType::Server);

    $response = $this->postJson('/api/reports', [
        'client_id' => $client->id,
        'period_start' => now()->subDays(7)->toDateString(),
        'period_end' => now()->toDateString(),
    ])->assertCreated();

    expect($response->json('health_score'))->toBeNull();

    $html = $this->get('/api/reports/'.$response->json('id').'/html')->assertOk();

    expect($html->getContent())
        ->toContain('Sin datos')
        ->toContain('no contiene datos de monitorización');
});

it('marca un informe como enviado y lo registra', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    $id = $this->postJson('/api/reports', [
        'client_id' => $client->id,
        'period_start' => now()->subDays(7)->toDateString(),
        'period_end' => now()->toDateString(),
    ])->json('id');

    $this->postJson('/api/reports/'.$id.'/mark-sent')
        ->assertOk()
        ->assertJsonPath('status', 'sent')
        ->assertJsonPath('sent_at', fn ($value) => $value !== null);
});
