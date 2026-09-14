<?php

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Domain\Enums\ReportStatus;
use App\Mail\ReportMail;
use App\Models\Account;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Report;
use App\Services\Reporting\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Construye el informe de un cliente con el periodo que cubre la evidencia de
 * prueba.
 */
function buildReportFor(Client $client): Report
{
    return app(ReportBuilder::class)->build(
        $client,
        CarbonImmutable::now()->subDays(10)->startOfDay(),
        CarbonImmutable::now()->endOfDay(),
    );
}

function clientWithEvidence(Account $account, array $attributes = []): Client
{
    $client = makeClient($account, $attributes);
    $asset = makeAsset($client, AssetType::Server);

    makeEvidence($asset, CheckType::DiskUsage, EvidenceStatus::Healthy, ['value_numeric' => 40.0]);
    makeEvidence($asset, CheckType::HttpStatus, EvidenceStatus::Healthy, ['value_numeric' => 200.0]);

    return $client;
}

it('descarga el informe como PDF válido', function () {
    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account);
    $report = buildReportFor($client);

    $response = $this->get('/api/reports/'.$report->id.'/pdf')->assertOk();

    $body = $response->getContent();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($body, 0, 4))->toBe('%PDF')
        ->and(strlen($body))->toBeGreaterThan(2000);
});

it('no expone el PDF de un informe de otra cuenta', function () {
    $otherAccount = Account::factory()->create();
    $foreignClient = clientWithEvidence($otherAccount);
    $report = buildReportFor($foreignClient);

    actingAsAccount();

    $this->get('/api/reports/'.$report->id.'/pdf')->assertNotFound();
});

it('envía el informe por correo al contacto del cliente', function () {
    Mail::fake();

    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account, ['contact_email' => 'cliente@example.test']);
    $report = buildReportFor($client);

    $this->postJson('/api/reports/'.$report->id.'/send')
        ->assertOk()
        ->assertJsonPath('sent_to', 'cliente@example.test');

    Mail::assertSent(ReportMail::class, fn (ReportMail $mail): bool => $mail->hasTo('cliente@example.test'));

    $fresh = $report->fresh();

    expect($fresh->status)->toBe(ReportStatus::Sent)
        ->and($fresh->sent_at)->not->toBeNull();
});

it('permite enviar a un destinatario puntual sin tocar el contacto del cliente', function () {
    Mail::fake();

    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account, ['contact_email' => null]);
    $report = buildReportFor($client);

    $this->postJson('/api/reports/'.$report->id.'/send', [
        'email' => 'otro@example.test',
        'note' => 'Te adjunto el informe del mes.',
    ])->assertOk()->assertJsonPath('sent_to', 'otro@example.test');

    Mail::assertSent(ReportMail::class, fn (ReportMail $mail): bool => $mail->hasTo('otro@example.test'));
});

it('exige un destinatario si el cliente no tiene correo de contacto', function () {
    Mail::fake();

    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account, ['contact_email' => null]);
    $report = buildReportFor($client);

    $this->postJson('/api/reports/'.$report->id.'/send')
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    Mail::assertNothingSent();
});

it('incluye el PDF como adjunto del correo', function () {
    Mail::fake();

    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account, ['contact_email' => 'cliente@example.test']);
    $report = buildReportFor($client);

    $this->postJson('/api/reports/'.$report->id.'/send')->assertOk();

    Mail::assertSent(ReportMail::class, function (ReportMail $mail): bool {
        $attachments = $mail->attachments();

        return count($attachments) === 1
            && $attachments[0]->mime === 'application/pdf';
    });
});

it('genera el informe del mes anterior para cada cliente activo', function () {
    ['account' => $account] = actingAsAccount();

    makeClient($account);
    makeClient($account);
    makeClient($account, ['active' => false]);

    $this->artisan('opsevidence:generate-monthly-reports')->assertSuccessful();

    expect(Report::withoutGlobalScopes()->count())->toBe(2)
        ->and(Report::withoutGlobalScopes()->first()->period_end->isLastOfMonth())->toBeTrue();
});

it('puede enviar los informes generados con --send y omite a quien no tiene correo', function () {
    Mail::fake();

    ['account' => $account] = actingAsAccount();

    makeClient($account, ['contact_email' => 'con-correo@example.test']);
    makeClient($account, ['contact_email' => null]);

    $this->artisan('opsevidence:generate-monthly-reports --send')->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(ReportMail::class, fn (ReportMail $mail): bool => $mail->hasTo('con-correo@example.test'));
});

it('rechaza un mes con formato inválido en el comando', function () {
    ['account' => $account] = actingAsAccount();
    makeClient($account);

    $this->artisan('opsevidence:generate-monthly-reports --month=agosto')->assertFailed();

    expect(Report::withoutGlobalScopes()->count())->toBe(0);
});

it('genera el informe de un mes concreto cuando se le indica', function () {
    ['account' => $account] = actingAsAccount();
    $client = clientWithEvidence($account);

    Evidence::withoutGlobalScopes()
        ->where('client_id', $client->id)
        ->update(['collected_at' => now()->subMonths(3)]);

    $month = now()->subMonths(3)->format('Y-m');

    $this->artisan('opsevidence:generate-monthly-reports --month='.$month)->assertSuccessful();

    $report = Report::withoutGlobalScopes()->firstOrFail();

    expect($report->period_start->format('Y-m'))->toBe($month);
});
