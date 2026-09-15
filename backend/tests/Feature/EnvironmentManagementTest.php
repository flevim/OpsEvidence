<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\EnvironmentType;
use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\Environment;

it('gestiona ambientes del cliente y expone su uso', function () {
    ['account' => $account] = actingAsAccount(UserRole::Owner);
    $client = makeClient($account);

    $created = $this->postJson('/api/clients/'.$client->id.'/environments', [
        'name' => 'Producción CL',
        'type' => EnvironmentType::Production->value,
    ])
        ->assertCreated()
        ->assertJsonPath('data.type_label', 'Producción')
        ->assertJsonPath('data.assets_count', 0);

    $environmentId = $created->json('data.id');

    $this->postJson('/api/clients/'.$client->id.'/assets', [
        'name' => 'web-produccion',
        'type' => AssetType::Website->value,
        'environment_id' => $environmentId,
    ])->assertCreated()->assertJsonPath('environment.id', $environmentId);

    $this->getJson('/api/clients/'.$client->id.'/environments')
        ->assertOk()
        ->assertJsonPath('data.0.assets_count', 1);

    $this->patchJson('/api/environments/'.$environmentId, [
        'name' => 'Producción principal',
        'type' => EnvironmentType::Staging->value,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Producción principal')
        ->assertJsonPath('data.type_label', 'Staging');

    $this->deleteJson('/api/environments/'.$environmentId)->assertOk();
    expect(Environment::find($environmentId))->toBeNull();
});

it('permite editar pero no eliminar ambientes a un técnico', function () {
    ['account' => $account] = actingAsAccount(UserRole::Technician);
    $client = makeClient($account);
    $environment = Environment::query()->create([
        'account_id' => $account->id,
        'client_id' => $client->id,
        'name' => 'Desarrollo',
        'type' => EnvironmentType::Development,
    ]);

    $this->patchJson('/api/environments/'.$environment->id, ['name' => 'Desarrollo interno'])
        ->assertOk();
    $this->deleteJson('/api/environments/'.$environment->id)->assertForbidden();
});

it('aísla los ambientes entre cuentas', function () {
    actingAsAccount(UserRole::Owner);
    $otherAccount = Account::factory()->create();
    $otherClient = makeClient($otherAccount);
    $foreign = Environment::withoutGlobalScopes()->create([
        'account_id' => $otherAccount->id,
        'client_id' => $otherClient->id,
        'name' => 'Ajeno',
        'type' => EnvironmentType::Other,
    ]);

    $this->patchJson('/api/environments/'.$foreign->id, ['name' => 'Intruso'])->assertNotFound();
    $this->deleteJson('/api/environments/'.$foreign->id)->assertNotFound();
});
