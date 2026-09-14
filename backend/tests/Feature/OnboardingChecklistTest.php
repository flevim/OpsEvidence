<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Account;
use App\Models\Report;
use App\Services\Onboarding\ClientOnboardingChecklist;

it('señala el correo de contacto como el primer paso pendiente de un cliente nuevo', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account, ['contact_email' => null]);

    $result = app(ClientOnboardingChecklist::class)->forClient($client);

    expect($result['is_complete'])->toBeFalse()
        ->and($result['completed'])->toBe(0)
        ->and($result['completion'])->toBe(0)
        ->and($result['next_step']['key'])->toBe('contact')
        ->and($result['steps'])->toHaveCount(9)
        ->and($result['counts']['assets'])->toBe(0);
});

it('avanza a medida que se conectan activos y comprobaciones', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account, ['contact_email' => 'c@example.test']);
    $asset = makeAsset($client, AssetType::Server);
    makeCheck($asset, CheckType::DiskUsage);

    $result = app(ClientOnboardingChecklist::class)->forClient($client);

    expect($result['completed'])->toBe(3)
        ->and($result['next_step']['key'])->toBe('first_evidence')
        ->and($result['counts']['checks'])->toBe(1)
        ->and($result['counts']['checks_without_data'])->toBe(1);
});

it('explica cuántas comprobaciones siguen sin reportar datos', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account, ['contact_email' => 'c@example.test']);
    $asset = makeAsset($client, AssetType::Server);

    makeCheck($asset, CheckType::DiskUsage, ['last_success_at' => now()]);
    makeCheck($asset, CheckType::CpuUsage);
    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy);

    $result = app(ClientOnboardingChecklist::class)->forClient($client);
    $coverage = collect($result['steps'])->firstWhere('key', 'coverage');

    expect($coverage['done'])->toBeFalse()
        ->and($coverage['hint'])->toContain('1 de 2');
});

it('marca el checklist como completo cuando todo está conectado', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account, ['contact_email' => 'cliente@example.test']);
    $asset = makeAsset($client, AssetType::Server);
    makeCheck($asset, CheckType::DiskUsage, ['last_success_at' => now()]);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy);
    makeEvidence($asset, CheckType::BackupStatus, EvidenceStatus::Healthy);

    Report::create([
        'account_id' => $client->account_id,
        'client_id' => $client->id,
        'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        'period_end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $result = app(ClientOnboardingChecklist::class)->forClient($client);

    expect($result['is_complete'])->toBeTrue()
        ->and($result['completion'])->toBe(100)
        ->and($result['next_step'])->toBeNull();
});

it('no marca el paso del agente si la evidencia no viene del agente', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account, ['contact_email' => 'c@example.test']);
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy, ['source' => 'check']);

    $result = app(ClientOnboardingChecklist::class)->forClient($client);
    $agent = collect($result['steps'])->firstWhere('key', 'agent');

    expect($agent['done'])->toBeFalse();
});

it('expone el checklist en el resumen del cliente', function () {
    ['account' => $account] = actingAsAccount();
    $client = makeClient($account);

    $this->getJson('/api/clients/'.$client->id.'/summary')
        ->assertOk()
        ->assertJsonPath('onboarding.total', 9)
        ->assertJsonPath('onboarding.steps.0.key', 'contact')
        ->assertJsonStructure(['onboarding' => ['completed', 'completion', 'is_complete', 'next_step', 'steps']]);
});

it('no filtra el checklist de un cliente de otra cuenta', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = makeClient($otherAccount);

    actingAsAccount();

    $this->getJson('/api/clients/'.$foreignClient->id.'/summary')->assertNotFound();
});
