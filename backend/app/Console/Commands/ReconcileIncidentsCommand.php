<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateClientRulesJob;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Reconciliacion periodica del motor de reglas.
 *
 * Los checks ya disparan la evaluacion al terminar, pero hay reglas cuyo
 * incumplimiento no depende de una recoleccion nueva (un backup que dejo de
 * llegar, un servidor que dejo de reportar). Esta pasada las cubre.
 */
class ReconcileIncidentsCommand extends Command
{
    protected $signature = 'opsevidence:reconcile-incidents';

    protected $description = 'Reevalúa las reglas de todos los clientes y sincroniza sus incidentes.';

    public function handle(): int
    {
        $clients = Client::withoutGlobalScopes()->where('active', true)->get();

        foreach ($clients as $client) {
            EvaluateClientRulesJob::dispatch($client->id);
        }

        $this->info("Clientes enviados a reevaluación: {$clients->count()}");

        return self::SUCCESS;
    }
}
