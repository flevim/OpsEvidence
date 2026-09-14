<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;

/**
 * Cobertura de los endpoints de activos.
 *
 * El listado de activos de un cliente propio no estaba cubierto: los tests de
 * aislamiento solo golpeaban la ruta con un cliente ajeno (que responde 404
 * antes de llegar al controlador), por lo que un error de ordenamiento en los
 * endpoints anidados pasó desapercibido.
 */
it('lista los activos de un cliente propio', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    makeAsset($client);
    makeAsset($client, AssetType::Website);

    $this->getJson('/api/clients/'.$client->id.'/assets')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'name', 'type']],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('ordena los activos por una columna permitida y descarta las no permitidas', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    makeAsset($client, AssetType::Server, ['name' => 'aaa-server']);
    makeAsset($client, AssetType::Website, ['name' => 'zzz-site']);

    $ascending = $this->getJson('/api/clients/'.$client->id.'/assets?sort=name&order=asc')->assertOk();
    expect($ascending->json('data.0.name'))->toBe('aaa-server');

    // Una columna fuera de la lista blanca no rompe ni se interpola: cae al orden por defecto.
    $this->getJson('/api/clients/'.$client->id.'/assets?sort=password&order=asc')->assertOk();
});

it('crea un activo en un cliente propio', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    $this->postJson('/api/clients/'.$client->id.'/assets', [
        'name' => 'web-01',
        'type' => AssetType::Server->value,
        'hostname' => 'web-01.example.test',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'web-01')
        ->assertJsonPath('type', AssetType::Server->value);
});

it('exige un nombre de activo único dentro del cliente', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    makeAsset($client, AssetType::Server, ['name' => 'duplicado']);

    $this->postJson('/api/clients/'.$client->id.'/assets', [
        'name' => 'duplicado',
        'type' => AssetType::Server->value,
    ])->assertStatus(422)->assertJsonValidationErrors('name');
});

it('lista los checks de un activo indicando su frescura', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);
    $asset = makeAsset($client);

    makeCheck($asset, CheckType::DiskUsage);

    $this->getJson('/api/assets/'.$asset->id.'/checks')
        ->assertOk()
        ->assertJsonPath('data.0.freshness', 'never_collected')
        ->assertJsonPath('data.0.type_label', 'Uso de disco');
});

it('lista la evidencia de un activo', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);
    $asset = makeAsset($client);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy);

    $this->getJson('/api/assets/'.$asset->id.'/evidence')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('rechaza un check incompatible con el tipo de activo', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);
    $website = makeAsset($client, AssetType::Website);

    $this->postJson('/api/assets/'.$website->id.'/checks', [
        'type' => CheckType::DiskUsage->value,
        'name' => 'Uso de disco',
    ])->assertStatus(422)->assertJsonValidationErrors('type');
});

it('devuelve 404 al listar activos de un cliente inexistente', function () {
    actingAsAccount();

    $this->getJson('/api/clients/999999/assets')->assertNotFound();
});

it('expone el catálogo de tipos de check con sus activos compatibles', function () {
    actingAsAccount();

    $response = $this->getJson('/api/check-types')->assertOk();

    $types = collect($response->json('data'))->keyBy('value');

    expect($types)->toHaveCount(14)
        ->and($types['HTTP_STATUS']['asset_types'])->toContain('WEBSITE')
        ->and($types['HTTP_STATUS']['collected_by_platform'])->toBeTrue()
        ->and($types['DISK_USAGE']['asset_types'])->toContain('SERVER')
        ->and($types['DISK_USAGE']['collected_by_platform'])->toBeFalse()
        ->and($types['GITHUB_WORKFLOW']['asset_types'])->toBe(['REPOSITORY']);
});
