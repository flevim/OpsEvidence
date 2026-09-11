<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\Rules\IncidentManager;
use App\Services\Rules\RulesEngine;
use App\Support\AccountContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Evalua las reglas de un cliente y sincroniza sus incidentes.
 *
 * Se agrupa por cliente con una ventana minima de 30 segundos: una tanda de
 * 50 checks genera una sola evaluacion, no 50.
 */
class EvaluateClientRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public readonly int $clientId)
    {
        $this->onQueue('rules');
    }

    public function handle(RulesEngine $engine, IncidentManager $incidents): void
    {
        $lock = Cache::lock('rules:evaluate:'.$this->clientId, 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $client = Client::withoutGlobalScopes()->find($this->clientId);

            if ($client === null) {
                return;
            }

            AccountContext::run($client->account_id, function () use ($client, $engine, $incidents): void {
                $context = $engine->evaluateClient($client);

                $incidents->sync($client->account_id, $client->id, $context->violations());
            });
        } finally {
            $lock->release();
        }
    }
}
