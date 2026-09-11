<?php

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Account;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Integration;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Evidence\EvidencePayload;
use Carbon\CarbonImmutable;

it('normaliza en el servidor el estado que reporta el agente', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create(['name' => 'web-server-01']);
    $check = makeCheck($asset, CheckType::DiskUsage);

    [$plain] = ApiToken::issue($account, 'agente', clientId: $client->id, assetId: $asset->id);

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/agent/evidence', [
            'asset' => $asset->name,
            'hostname' => 'web-server-01',
            'evidence' => [
                ['type' => 'DISK_USAGE', 'data' => ['mountpoint' => '/', 'used_percent' => 95]],
            ],
        ])
        ->assertStatus(202)
        ->assertJsonPath('accepted', 1);

    $evidence = Evidence::withoutGlobalScopes()->firstOrFail();

    // El agente no envio ningun estado: CRITICAL lo decidio el servidor a
    // partir de los datos y los umbrales del check.
    expect($evidence->status)->toBe(EvidenceStatus::Critical)
        ->and($evidence->source)->toBe(EvidenceSource::Agent)
        ->and($evidence->value_numeric)->toBe(95.0)
        ->and($evidence->raw_data)->toHaveKey('mountpoint');

    $check->refresh();
    expect($check->last_status)->toBe(EvidenceStatus::Critical)
        ->and($check->last_success_at)->not->toBeNull()
        ->and($check->freshness())->toBe(Check::FRESH);
});

it('rechaza la ingesta del agente sin token válido', function () {
    $this->postJson('/api/agent/evidence', [
        'evidence' => [['type' => 'DISK_USAGE', 'data' => ['used_percent' => 10]]],
    ])->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer ops_token-inventado')
        ->postJson('/api/agent/evidence', [
            'evidence' => [['type' => 'DISK_USAGE', 'data' => ['used_percent' => 10]]],
        ])->assertUnauthorized();

    expect(Evidence::withoutGlobalScopes()->count())->toBe(0);
});

it('deja de aceptar evidencia cuando el token se revoca', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create();

    [$plain, $token] = ApiToken::issue($account, 'agente', clientId: $client->id, assetId: $asset->id);

    $token->revoke();

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/agent/evidence', [
            'asset' => $asset->name,
            'evidence' => [['type' => 'CPU_USAGE', 'data' => ['percent' => 10]]],
        ])
        ->assertUnauthorized();
});

it('registra un backup mediante webhook y lo normaliza', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create(['name' => 'postgres-production']);

    [$plain] = ApiToken::issue($account, 'backup', clientId: $client->id);

    $this->postJson('/api/webhooks/backup/'.$plain, [
        'source' => 'postgres-production',
        'status' => 'success',
        'size' => 1258291200,
        'duration_seconds' => 83,
    ])->assertStatus(202)->assertJsonPath('accepted', 1);

    $evidence = Evidence::withoutGlobalScopes()->firstOrFail();

    expect($evidence->type)->toBe(CheckType::BackupStatus)
        ->and($evidence->status)->toBe(EvidenceStatus::Healthy)
        ->and($evidence->source)->toBe(EvidenceSource::Webhook)
        ->and($evidence->data)->toMatchArray(['duration_seconds' => 83]);
});

it('marca como crítico un backup fallido por webhook', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create(['name' => 'backup-01']);

    [$plain] = ApiToken::issue($account, 'backup', clientId: $client->id);

    $this->postJson('/api/webhooks/backup/'.$plain, [
        'source' => 'backup-01',
        'status' => 'failed',
        'message' => 'pg_dump salió con código 1',
    ])->assertStatus(202);

    expect(Evidence::withoutGlobalScopes()->firstOrFail()->status)->toBe(EvidenceStatus::Critical);
});

it('rechaza un webhook con token inválido', function () {
    $this->postJson('/api/webhooks/backup/token-falso', [
        'source' => 'cualquiera',
        'status' => 'success',
    ])->assertUnauthorized();

    expect(Evidence::withoutGlobalScopes()->count())->toBe(0);
});

it('exige que el origen del backup exista en el cliente del token', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();

    [$plain] = ApiToken::issue($account, 'backup', clientId: $client->id);

    $this->postJson('/api/webhooks/backup/'.$plain, [
        'source' => 'no-existe',
        'status' => 'success',
    ])->assertStatus(422)->assertJsonValidationErrors('source');
});

it('no duplica evidencia cuando el mismo check se ingesta dos veces', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create();
    $check = makeCheck($asset, CheckType::DiskUsage);

    $ingestor = app(EvidenceIngestor::class);
    $collectedAt = CarbonImmutable::now();

    $payload = EvidencePayload::make(
        type: CheckType::DiskUsage,
        status: EvidenceStatus::Healthy,
        title: 'Uso de disco: 40 %',
        options: [
            'value_numeric' => 40.0,
            'unit' => '%',
            'data' => ['mountpoint' => '/', 'used_percent' => 40.0],
            'collected_at' => $collectedAt,
        ],
    );

    $first = $ingestor->ingestForCheck($check, [$payload]);
    $second = $ingestor->ingestForCheck($check, [$payload]);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and(Evidence::withoutGlobalScopes()->count())->toBe(1);
});

it('no permite modificar la evidencia una vez registrada', function () {
    $account = Account::factory()->create();
    $client = Client::factory()->forAccount($account)->create();
    $asset = Asset::factory()->forClient($client)->create();

    $evidence = makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy);

    expect(fn () => $evidence->update(['title' => 'Manipulado']))
        ->toThrow(RuntimeException::class);
});

it('aclara el origen del token en la lista de tokens', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    $this->postJson('/api/api-tokens', [
        'name' => 'servidor-01',
        'client_id' => $client->id,
    ])->assertCreated()
        ->assertJsonStructure(['id', 'prefix', 'token', 'warning']);

    // El token en claro se devuelve una sola vez y nunca se vuelve a exponer.
    $list = $this->getJson('/api/api-tokens')->assertOk();
    expect($list->json('data.0'))->not->toHaveKey('token');
});

it('no expone las credenciales de una integración', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    $response = $this->postJson('/api/integrations', [
        'client_id' => $client->id,
        'type' => 'github',
        'name' => 'GitHub de ejemplo',
        'configuration' => ['owner' => 'acme', 'repo' => 'backend'],
        'credentials' => ['token' => 'ghp_secreto_que_no_debe_salir'],
    ])->assertCreated();

    expect($response->json())->not->toHaveKey('credentials')
        ->and($response->json('has_credentials'))->toBeTrue()
        ->and($response->getContent())->not->toContain('ghp_secreto_que_no_debe_salir');

    $stored = Integration::withoutGlobalScopes()->findOrFail($response->json('id'));
    expect($stored->getRawOriginal('credentials'))->not->toContain('ghp_secreto_que_no_debe_salir');
});
