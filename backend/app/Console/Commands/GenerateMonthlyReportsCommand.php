<?php

namespace App\Console\Commands;

use App\Domain\Enums\ReportStatus;
use App\Mail\ReportMail;
use App\Models\Client;
use App\Services\Reporting\ReportBuilder;
use App\Services\Reporting\ReportPdfRenderer;
use App\Support\AccountContext;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Genera el informe del mes para cada cliente activo.
 *
 * Es lo que convierte OpsEvidence en algo que trabaja solo: el dia 1 de cada
 * mes el informe ya esta listo (y, con --send, entregado).
 */
class GenerateMonthlyReportsCommand extends Command
{
    protected $signature = 'opsevidence:generate-monthly-reports
                            {--month= : Mes a generar en formato Y-m (por defecto, el mes anterior)}
                            {--send : Enviar el informe por correo al contacto del cliente}';

    protected $description = 'Genera (y opcionalmente envía) el informe mensual de cada cliente activo.';

    public function handle(ReportBuilder $builder, ReportPdfRenderer $pdf): int
    {
        $from = $this->resolveMonth();

        if ($from === null) {
            return self::FAILURE;
        }

        $to = $from->endOfMonth();
        $shouldSend = (bool) $this->option('send');

        $clients = Client::withoutGlobalScopes()->where('active', true)->get();

        if ($clients->isEmpty()) {
            $this->warn('No hay clientes activos.');

            return self::SUCCESS;
        }

        $generated = 0;
        $sent = 0;

        foreach ($clients as $client) {
            AccountContext::run($client->account_id, function () use ($client, $builder, $pdf, $from, $to, $shouldSend, &$generated, &$sent): void {
                $report = $builder->build($client, $from, $to);
                $generated++;

                $this->line(sprintf(
                    '  %s — score %s',
                    $client->name,
                    $report->health_score !== null ? $report->health_score.'/100' : 'sin datos',
                ));

                if (! $shouldSend) {
                    return;
                }

                if (blank($client->contact_email)) {
                    $this->warn("    {$client->name}: sin correo de contacto, no se envía.");

                    return;
                }

                Mail::to($client->contact_email)->send(
                    new ReportMail($report, $pdf->render($report)),
                );

                $report->forceFill([
                    'status' => ReportStatus::Sent,
                    'sent_at' => now(),
                ])->save();

                $sent++;
            });
        }

        $this->info("Informes generados: {$generated} (periodo {$from->format('Y-m')})");

        if ($shouldSend) {
            $this->info("Informes enviados por correo: {$sent}");
        }

        return self::SUCCESS;
    }

    private function resolveMonth(): ?CarbonImmutable
    {
        $option = $this->option('month');

        if ($option === null) {
            return CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        }

        try {
            $month = CarbonImmutable::createFromFormat('!Y-m', (string) $option);
        } catch (InvalidFormatException) {
            $month = false;
        }

        if ($month === false) {
            $this->error('El formato de --month debe ser Y-m, por ejemplo 2026-08.');

            return null;
        }

        return $month->startOfMonth();
    }
}
