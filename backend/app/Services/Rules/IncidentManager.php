<?php

namespace App\Services\Rules;

use App\Domain\Enums\IncidentStatus;
use App\Models\Incident;
use App\Support\AccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convierte incumplimientos de reglas en incidentes.
 *
 * Reglas del juego:
 *  - Un incidente vivo por (cliente, regla, activo), garantizado por indice
 *    unico parcial. Nunca se generan 288 incidentes al dia por una regla que
 *    se repite cada 5 minutos.
 *  - Si la condicion desaparece, el incidente se resuelve solo.
 *  - Un incidente reconocido o resuelto a mano no se reabre: si la condicion
 *    vuelve, se crea uno nuevo. Asi el historial cuenta la verdad.
 */
class IncidentManager
{
    /**
     * @param  array<int, RuleViolation>  $violations
     * @return array{opened: int, updated: int, resolved: int}
     */
    public function sync(int $accountId, int $clientId, array $violations): array
    {
        return AccountContext::run($accountId, function () use ($accountId, $clientId, $violations): array {
            $active = Incident::query()
                ->where('client_id', $clientId)
                ->whereIn('status', [IncidentStatus::Open->value, IncidentStatus::Acknowledged->value])
                ->get()
                ->keyBy('signature');

            $opened = 0;
            $updated = 0;
            $seen = [];

            foreach ($violations as $violation) {
                $signature = $violation->signature();
                $seen[] = $signature;

                $existing = $active->get($signature);

                if ($existing === null) {
                    if ($this->open($accountId, $clientId, $violation)) {
                        $opened++;
                    }

                    continue;
                }

                if ($this->refresh($existing, $violation)) {
                    $updated++;
                }
            }

            $resolved = $this->resolveStale($active, $seen);

            return [
                'opened' => $opened,
                'updated' => $updated,
                'resolved' => $resolved,
            ];
        });
    }

    private function open(int $accountId, int $clientId, RuleViolation $violation): bool
    {
        $now = CarbonImmutable::now();

        try {
            DB::table('incidents')->insert([
                'account_id' => $accountId,
                'client_id' => $clientId,
                'asset_id' => $violation->assetId,
                'rule_key' => $violation->rule->value,
                'signature' => $violation->signature(),
                'severity' => $violation->severity->value,
                'status' => IncidentStatus::Open->value,
                'title' => $violation->title,
                'description' => $violation->description,
                'evidence_id' => $violation->evidenceId,
                'opened_at' => $now,
                'metadata' => json_encode(['recommendation' => $violation->recommendation]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otro worker abrio el mismo incidente en paralelo: el indice unico
            // parcial hizo su trabajo.
            return false;
        }

        return true;
    }

    private function refresh(Incident $incident, RuleViolation $violation): bool
    {
        $changed = $incident->severity !== $violation->severity
            || $incident->title !== $violation->title
            || $incident->evidence_id !== $violation->evidenceId;

        if (! $changed) {
            return false;
        }

        if ($incident->severity !== $violation->severity && $incident->status === IncidentStatus::Acknowledged) {
            // La gravedad cambio tras reconocerlo: vuelve a abierto para que
            // alguien lo mire con la informacion nueva.
            $incident->status = IncidentStatus::Open;
            $incident->acknowledged_at = null;
            $incident->acknowledged_by = null;
        }

        $incident->severity = $violation->severity;
        $incident->title = $violation->title;
        $incident->description = $violation->description;
        $incident->evidence_id = $violation->evidenceId;
        $incident->metadata = array_merge($incident->metadata ?? [], [
            'recommendation' => $violation->recommendation,
        ]);
        $incident->save();

        return true;
    }

    /**
     * @param  Collection<string, Incident>  $active
     * @param  array<int, string>  $seen
     */
    private function resolveStale(Collection $active, array $seen): int
    {
        $resolved = 0;

        foreach ($active as $signature => $incident) {
            if (in_array($signature, $seen, true) || $incident->status !== IncidentStatus::Open) {
                continue;
            }

            $incident->forceFill([
                'status' => IncidentStatus::Resolved,
                'resolved_at' => CarbonImmutable::now(),
                'resolution_note' => 'Resuelto automáticamente: la condición dejó de cumplirse.',
            ])->save();

            $resolved++;
        }

        return $resolved;
    }
}
