<?php

namespace App\Console\Commands;

use App\Jobs\RunCheckJob;
use App\Models\Check;
use App\Services\Collectors\CollectorRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Encola los checks vencidos.
 *
 * Se agrupa por cuenta para evitar que un tenant con miles de checks acapare
 * la cola y degrade a los demas (ver docs/architecture.md, seccion 6).
 */
class DispatchChecksCommand extends Command
{
    protected $signature = 'opsevidence:dispatch-checks {--limit=2000 : Máximo de checks a despachar por ejecución}';

    protected $description = 'Despacha los checks vencidos a la cola de recolección.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $checks = Check::withoutGlobalScopes()
            ->where('enabled', true)
            ->where(function ($query): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->whereHas('asset', fn ($query) => $query->where('active', true))
            ->orderBy('account_id')
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get();

        $dispatched = 0;
        $skipped = 0;

        foreach ($checks as $check) {
            if (! app(CollectorRegistry::class)->supports($check->type)) {
                $skipped++;

                continue;
            }

            // Se adelanta next_run_at antes de encolar: si el worker tarda, el
            // scheduler no vuelve a encolar el mismo check cada minuto.
            $check->forceFill([
                'next_run_at' => now()->addSeconds($check->nextRunInSeconds()),
            ])->saveQuietly();

            RunCheckJob::dispatch($check->id);
            $dispatched++;
        }

        Cache::put('opsevidence:last_dispatch', [
            'at' => now()->toIso8601String(),
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ], now()->addDay());

        $this->info("Checks despachados: {$dispatched} (omitidos sin collector: {$skipped})");

        return self::SUCCESS;
    }
}
