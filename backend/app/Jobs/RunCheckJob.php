<?php

namespace App\Jobs;

use App\Domain\Enums\CheckRunStatus;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Check;
use App\Models\CheckRun;
use App\Services\Collectors\CollectorRegistry;
use App\Services\Evidence\EvidenceIngestor;
use App\Services\Evidence\EvidencePayload;
use App\Support\AccountContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Ejecuta un check de la plataforma y guarda su evidencia.
 *
 * Idempotente: si el job se reintenta, el indice unico de dedup_key impide
 * duplicar la evidencia.
 */
class RunCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    public function __construct(public readonly int $checkId)
    {
        $this->onQueue('checks');
    }

    public function handle(CollectorRegistry $registry, EvidenceIngestor $ingestor): void
    {
        $check = Check::withoutGlobalScopes()->with('asset')->find($this->checkId);

        if ($check === null || ! $check->enabled) {
            return;
        }

        AccountContext::run($check->account_id, function () use ($check, $registry, $ingestor): void {
            $run = CheckRun::create([
                'account_id' => $check->account_id,
                'check_id' => $check->id,
                'status' => CheckRunStatus::Running->value,
                'started_at' => now(),
            ]);

            try {
                $collector = $registry->resolveOrFail($check);
                $payloads = $collector->collect($check);

                $stored = $ingestor->ingestForCheck($check, $payloads, $run);

                $run->finish(CheckRunStatus::Success);

                if ($stored !== []) {
                    EvaluateClientRulesJob::dispatch($check->client_id);
                }
            } catch (Throwable $exception) {
                $message = mb_substr($exception->getMessage(), 0, 1000);

                $run->finish(CheckRunStatus::Failed, $message);
                $check->markFailure($message);

                // Solo se registra un FAILED si el activo ya tenia datos: si
                // nunca hubo evidencia, el check queda en "sin datos", que es
                // mas honesto que un cero (ver docs/evidence-model.md).
                if ($check->last_success_at !== null) {
                    $ingestor->ingestForCheck($check, [
                        EvidencePayload::make(
                            type: $check->type,
                            status: EvidenceStatus::Failed,
                            title: 'No se pudo recolectar la evidencia: '.$check->name,
                            options: [
                                'raw_status' => 'collection_failed',
                                'data' => ['error' => $message],
                                'raw_data' => ['error' => $message],
                                'source' => EvidenceSource::Check,
                            ],
                        ),
                    ], $run);
                }

                EvaluateClientRulesJob::dispatch($check->client_id);

                report($exception);
            }
        });
    }
}
