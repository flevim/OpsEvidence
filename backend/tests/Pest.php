<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\UserRole;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\User;
use App\Support\AccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// La suite corre contra PostgreSQL: el esquema usa JSONB, indices parciales y
// agregaciones que SQLite no reproduce fielmente.
uses(RefreshDatabase::class)->in('Feature');

/**
 * Crea (o reutiliza) una cuenta, crea un usuario con el rol indicado y
 * autentica la peticion.
 *
 * @return array{account: Account, user: User}
 */
function actingAsAccount(UserRole $role = UserRole::Owner, ?Account $account = null): array
{
    $account ??= Account::factory()->create();
    $user = User::factory()->forAccount($account, $role)->create();

    Sanctum::actingAs($user);

    AccountContext::set($account->id);

    return ['account' => $account, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeClient(Account $account, array $attributes = []): Client
{
    return Client::factory()->forAccount($account)->create($attributes);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeAsset(Client $client, AssetType $type = AssetType::Server, array $attributes = []): Asset
{
    return Asset::factory()->forClient($client)->ofType($type)->create($attributes);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCheck(Asset $asset, CheckType $type, array $attributes = []): Check
{
    return Check::factory()->forAsset($asset)->ofType($type)->create($attributes);
}

/**
 * Inserta evidencia saltandose el ingestor, para preparar escenarios.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeEvidence(Asset $asset, CheckType $type, EvidenceStatus $status, array $overrides = []): Evidence
{
    $collectedAt = $overrides['collected_at'] ?? CarbonImmutable::now()->subMinutes(5);
    unset($overrides['collected_at']);

    return Evidence::factory()
        ->forAsset($asset)
        ->ofType($type)
        ->withStatus($status)
        ->collectedAt($collectedAt)
        ->dedupKey(hash('sha256', $asset->id.'|'.$type->value.'|'.$collectedAt->getTimestamp().'|'.uniqid('', true)))
        ->create($overrides);
}
