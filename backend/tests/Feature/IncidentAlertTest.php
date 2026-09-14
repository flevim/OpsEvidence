<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\UserRole;
use App\Mail\IncidentAlertMail;
use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * El informe mensual demuestra lo que pasó; la alerta evita que pase. Sin ella,
 * un incidente crítico espera a que alguien entre al panel por casualidad.
 */
function clientWithCriticalDisk(Account $account): Client
{
    $client = makeClient($account);
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Critical, ['value_numeric' => 96.0]);

    return $client;
}

it('avisa al equipo cuando se abre un incidente', function () {
    Mail::fake();

    $account = Account::factory()->create();
    User::factory()->forAccount($account, UserRole::Owner)->create(['email' => 'owner@example.test']);
    User::factory()->forAccount($account, UserRole::Technician)->create(['email' => 'tecnico@example.test']);

    evaluateAndSync(clientWithCriticalDisk($account));

    Mail::assertSent(IncidentAlertMail::class, fn (IncidentAlertMail $mail): bool => $mail->hasTo('owner@example.test'));
    Mail::assertSent(IncidentAlertMail::class, fn (IncidentAlertMail $mail): bool => $mail->hasTo('tecnico@example.test'));
});

it('no molesta a los usuarios de solo lectura', function () {
    Mail::fake();

    $account = Account::factory()->create();
    User::factory()->forAccount($account, UserRole::Viewer)->create(['email' => 'lector@example.test']);

    evaluateAndSync(clientWithCriticalDisk($account));

    Mail::assertNothingSent();
});

it('no manda alertas si la cuenta no tiene destinatarios', function () {
    Mail::fake();

    evaluateAndSync(clientWithCriticalDisk(Account::factory()->create()));

    Mail::assertNothingSent();
});

it('respeta el buzón de alertas configurado en la cuenta', function () {
    Mail::fake();

    $account = Account::factory()->create(['settings' => ['alert_email' => 'guardia@example.test']]);

    evaluateAndSync(clientWithCriticalDisk($account));

    Mail::assertSent(IncidentAlertMail::class, fn (IncidentAlertMail $mail): bool => $mail->hasTo('guardia@example.test'));
});

it('no repite la alerta en reevaluaciones posteriores', function () {
    Mail::fake();

    $account = Account::factory()->create();
    User::factory()->forAccount($account, UserRole::Owner)->create(['email' => 'owner@example.test']);

    $client = clientWithCriticalDisk($account);

    evaluateAndSync($client);
    evaluateAndSync($client);
    evaluateAndSync($client);

    // Un incidente que sigue abierto no vuelve a avisar: solo avisa el momento
    // en que se abre.
    Mail::assertSentCount(1);
});
