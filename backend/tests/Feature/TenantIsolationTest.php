<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\ApiToken;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Incident;
use App\Support\AccountContext;

/**
 * La propiedad mas importante del producto: una cuenta nunca debe poder ver ni
 * tocar datos de otra. Se prueban las tres capas (global scope, policy y
 * middleware de pertenencia) mediante la respuesta HTTP.
 */
it('no lista clientes de otra cuenta', function () {
    $otherAccount = Account::factory()->create();
    Client::factory()->forAccount($otherAccount)->count(3)->create();

    actingAsAccount();

    $this->getJson('/api/clients')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('devuelve 404 al pedir un cliente de otra cuenta', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();

    actingAsAccount();

    $this->getJson('/api/clients/'.$foreignClient->id)->assertNotFound();
    $this->getJson('/api/clients/999999')->assertNotFound();
});

it('responde 404 y no 500 ante un identificador no numérico', function () {
    actingAsAccount();

    $this->getJson('/api/clients/no-es-un-id')->assertNotFound();
    $this->getJson('/api/assets/abc')->assertNotFound();
});

it('no permite modificar ni borrar recursos de otra cuenta', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();
    $foreignAsset = Asset::factory()->forClient($foreignClient)->create();

    actingAsAccount(UserRole::Owner);

    $this->patchJson('/api/clients/'.$foreignClient->id, ['name' => 'Secuestrado'])->assertNotFound();
    $this->deleteJson('/api/clients/'.$foreignClient->id)->assertNotFound();
    $this->getJson('/api/assets/'.$foreignAsset->id)->assertNotFound();
    $this->patchJson('/api/assets/'.$foreignAsset->id, ['name' => 'Secuestrado'])->assertNotFound();
});

it('no filtra activos ni checks ajenos', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();
    $foreignAsset = Asset::factory()->forClient($foreignClient)->create();
    $foreignCheck = Check::factory()->forAsset($foreignAsset)->ofType(CheckType::DiskUsage)->create();

    actingAsAccount(UserRole::Owner);

    $this->getJson('/api/assets')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/clients/'.$foreignClient->id.'/assets')->assertNotFound();
    $this->getJson('/api/checks/'.$foreignCheck->id)->assertNotFound();
    $this->postJson('/api/checks/'.$foreignCheck->id.'/run')->assertNotFound();
});

it('no filtra evidencia ni incidentes ajenos', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();
    $foreignAsset = Asset::factory()->forClient($foreignClient)->create();

    makeEvidence($foreignAsset, CheckType::DiskUsage, EvidenceStatus::Critical);

    Incident::factory()->create([
        'account_id' => $otherAccount->id,
        'client_id' => $foreignClient->id,
        'asset_id' => $foreignAsset->id,
    ]);

    actingAsAccount(UserRole::Owner);

    $this->getJson('/api/evidence')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/incidents')->assertOk()->assertJsonPath('meta.total', 0);
});

it('no permite reutilizar un id de cliente de otra cuenta al crear un activo', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();

    actingAsAccount(UserRole::Owner);

    $this->postJson('/api/clients/'.$foreignClient->id.'/assets', [
        'name' => 'intruso-01',
        'type' => AssetType::Server->value,
    ])->assertNotFound();
});

it('no permite registrar evidencia contra un cliente ajeno', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = Client::factory()->forAccount($otherAccount)->create();
    $foreignAsset = Asset::factory()->forClient($foreignClient)->create();

    actingAsAccount(UserRole::Owner);

    $this->postJson('/api/evidence', [
        'client_id' => $foreignClient->id,
        'asset_id' => $foreignAsset->id,
        'type' => CheckType::DiskUsage->value,
        'status' => EvidenceStatus::Healthy->value,
        'title' => 'Inyectado',
    ])->assertNotFound();
});

it('aísla la ingesta del agente por token', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    $clientB = Client::factory()->forAccount($accountB)->create();
    $assetB = Asset::factory()->forClient($clientB)->create(['name' => 'servidor-b']);

    [$plain] = ApiToken::issue($accountA, 'agente-a');

    $this->withHeader('Authorization', 'Bearer '.$plain)
        ->postJson('/api/agent/evidence', [
            'client' => $clientB->slug,
            'asset' => $assetB->name,
            'evidence' => [
                ['type' => CheckType::DiskUsage->value, 'data' => ['mountpoint' => '/', 'used_percent' => 10]],
            ],
        ])
        ->assertStatus(422);

    expect(Evidence::withoutGlobalScopes()->count())->toBe(0);
});

it('aísla el contexto de cuenta entre peticiones', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    Client::factory()->forAccount($accountA)->create();
    Client::factory()->forAccount($accountB)->count(2)->create();

    actingAsAccount(UserRole::Owner, $accountA);
    $this->getJson('/api/clients')->assertJsonPath('meta.total', 1);

    // Al terminar la peticion el contexto debe quedar limpio.
    expect(AccountContext::current())->toBeNull();

    $this->getJson('/api/clients')->assertJsonPath('meta.total', 1);
});
