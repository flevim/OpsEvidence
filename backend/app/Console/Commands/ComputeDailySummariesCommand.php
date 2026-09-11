<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\DailySummary;
use App\Services\Reporting\ReportMetricsCollector;
use App\Support\AccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Condensa un dia de evidencia en una fila de metricas por cliente.
 *
 * Es lo que permite que el informe mensual no tenga que escanear millones de
 * filas de evidencia cruda (ver docs/database.md, seccion 8).
 */
class ComputeDailySummariesCommand extends Command
{
    protected $signature = 'opsevidence:compute-daily-summaries {--date= : Día a calcular (Y-m-d), por defecto ayer}';

    protected $description = 'Calcula los agregados diarios por cliente.';

    public function handle(ReportMetricsCollector $collector): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::yesterday();

        $from = $date->startOfDay();
        $to = $date->endOfDay();

        $clients = Client::withoutGlobalScopes()->get();
        $computed = 0;

        foreach ($clients as $client) {
            AccountContext::run($client->account_id, function () use ($client, $collector, $date, $from, $to, &$computed): void {
                $metrics = $collector->forPeriod($client, $from, $to);

                DailySummary::withoutGlobalScopes()->updateOrCreate(
                    ['client_id' => $client->id, 'date' => $date->toDateString()],
                    [
                        'account_id' => $client->account_id,
                        'metrics' => $metrics,
                        'computed_at' => now(),
                    ],
                );

                $computed++;
            });
        }

        $this->info("Agregados diarios calculados: {$computed} cliente(s) para {$date->toDateString()}");

        return self::SUCCESS;
    }
}
