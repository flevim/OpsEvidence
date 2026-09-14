<?php

namespace App\Services\Onboarding;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Client;
use App\Models\Evidence;
use App\Models\Report;

/**
 * Checklist de configuración de un cliente.
 *
 * Responde a la pregunta que hoy no tiene respuesta en el producto: "acabo de
 * crear el cliente, ¿ahora qué?". Todo se deriva del estado real (activos,
 * checks, evidencia, informes), no de una tabla de pasos que alguien deba
 * mantener sincronizada.
 */
class ClientOnboardingChecklist
{
    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        $enabledChecks = Check::query()
            ->where('client_id', $client->id)
            ->where('enabled', true)
            ->get(['id', 'last_success_at']);

        $checks = $enabledChecks->count();
        $checksWithData = $enabledChecks->whereNotNull('last_success_at')->count();
        $checksWithoutData = $checks - $checksWithData;

        $hasEvidence = Evidence::query()->where('client_id', $client->id)->exists();

        $hasAgentEvidence = Evidence::query()
            ->where('client_id', $client->id)
            ->where('source', EvidenceSource::Agent->value)
            ->exists();

        $hasBackupEvidence = Evidence::query()
            ->where('client_id', $client->id)
            ->where('type', CheckType::BackupStatus->value)
            ->exists();

        $hasReport = Report::query()->where('client_id', $client->id)->exists();

        $hasDelivered = Report::query()
            ->where('client_id', $client->id)
            ->whereNotNull('sent_at')
            ->exists();

        $assets = Asset::query()
            ->where('client_id', $client->id)
            ->where('active', true)
            ->count();

        $steps = [
            $this->step(
                'contact',
                'Datos de contacto',
                'Sin destinatario no hay a quién entregar el informe.',
                'Agrega el correo del contacto del cliente.',
                filled($client->contact_email),
            ),
            $this->step(
                'assets',
                'Registrar activos',
                'Servidores, sitios, aplicaciones o bases de datos que administras.',
                'Agrega al menos un activo al cliente.',
                $assets > 0,
            ),
            $this->step(
                'checks',
                'Configurar comprobaciones',
                'Cada activo necesita comprobaciones para producir evidencia.',
                'Crea checks en los activos (HTTP, SSL, disco, backups, Docker…).',
                $checks > 0,
            ),
            $this->step(
                'first_evidence',
                'Recibir la primera evidencia',
                'El primer dato real del cliente.',
                'Espera el primer ciclo de recolección o lanza un check a mano.',
                $hasEvidence,
            ),
            $this->step(
                'coverage',
                'Que todas las comprobaciones reporten',
                'Una comprobación sin datos no puede demostrar nada.',
                $checksWithoutData > 0
                    ? "{$checksWithoutData} de {$checks} comprobaciones todavía no reportan datos."
                    : 'Todas las comprobaciones están reportando.',
                $checks > 0 && $checksWithoutData === 0,
            ),
            $this->step(
                'agent',
                'Conectar el agente en los servidores',
                'Es lo que aporta CPU, memoria, disco, actualizaciones y contenedores.',
                'Instala el agente y emite un token en Ajustes (ver docs/linux-agent.md).',
                $hasAgentEvidence,
            ),
            $this->step(
                'backups',
                'Reportar backups',
                'Es lo primero que un cliente pregunta y lo que casi nadie demuestra.',
                'Añade el curl del webhook al final de tu script de backup.',
                $hasBackupEvidence,
            ),
            $this->step(
                'first_report',
                'Generar el primer informe',
                'El documento que justifica el trabajo del mes.',
                'Genera el informe del periodo en la pestaña Informes.',
                $hasReport,
            ),
            $this->step(
                'delivery',
                'Entregar el informe al cliente',
                'Un informe sin enviar no vale nada.',
                'Envíalo por correo desde la pestaña Informes.',
                $hasDelivered,
            ),
        ];

        $completed = count(array_filter($steps, static fn (array $step): bool => $step['done']));
        $total = count($steps);

        return [
            'completed' => $completed,
            'total' => $total,
            'completion' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'is_complete' => $completed === $total,
            'next_step' => $this->firstPending($steps),
            'steps' => $steps,
            'counts' => [
                'assets' => $assets,
                'checks' => $checks,
                'checks_without_data' => $checksWithoutData,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(string $key, string $label, string $description, string $hint, bool $done): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'hint' => $hint,
            'done' => $done,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    private function firstPending(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (! $step['done']) {
                return $step;
            }
        }

        return null;
    }
}
