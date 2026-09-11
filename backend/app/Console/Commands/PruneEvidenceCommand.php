<?php

namespace App\Console\Commands;

use App\Domain\Enums\ReportStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retencion de evidencia cruda.
 *
 * La evidencia es valiosa a corto plazo y voluminosa; el historico se conserva
 * agregado en daily_summaries y congelado en los informes. Los informes, los
 * incidentes y las actividades NO se tocan nunca.
 */
class PruneEvidenceCommand extends Command
{
    protected $signature = 'opsevidence:prune-evidence
                            {--days= : Días de retención (por defecto, el configurado)}
                            {--dry-run : Solo informa cuántas filas se borrarían}';

    protected $description = 'Elimina evidencia cruda y check_runs anteriores al periodo de retención.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('opsevidence.evidence.retention_days'));
        $runDays = (int) config('opsevidence.evidence.check_run_retention_days');
        $dryRun = (bool) $this->option('dry-run');

        $evidenceCutoff = now()->subDays($days);
        $runCutoff = now()->subDays($runDays);

        $evidenceQuery = DB::table('evidence')->where('collected_at', '<', $evidenceCutoff);
        $runsQuery = DB::table('check_runs')->where('started_at', '<', $runCutoff);

        $evidenceCount = (clone $evidenceQuery)->count();
        $runsCount = (clone $runsQuery)->count();

        if ($dryRun) {
            $this->info("[dry-run] Evidencia a eliminar: {$evidenceCount} (anteriores a {$evidenceCutoff->toDateString()})");
            $this->info("[dry-run] Ejecuciones a eliminar: {$runsCount} (anteriores a {$runCutoff->toDateString()})");

            return self::SUCCESS;
        }

        // Se borra en lotes para no bloquear la tabla ni inflar el WAL.
        $deletedEvidence = 0;

        do {
            $batch = DB::table('evidence')
                ->where('collected_at', '<', $evidenceCutoff)
                ->limit(5000)
                ->delete();

            $deletedEvidence += $batch;
        } while ($batch > 0);

        $deletedRuns = 0;

        do {
            $batch = DB::table('check_runs')
                ->where('started_at', '<', $runCutoff)
                ->limit(5000)
                ->delete();

            $deletedRuns += $batch;
        } while ($batch > 0);

        $this->info("Evidencia eliminada: {$deletedEvidence}");
        $this->info("Ejecuciones eliminadas: {$deletedRuns}");
        $this->line('Informes conservados: '.DB::table('reports')->where('status', ReportStatus::Sent->value)->count());

        return self::SUCCESS;
    }
}
