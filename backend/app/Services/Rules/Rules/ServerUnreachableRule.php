<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\AssetType;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Models\Asset;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * Servidores que dejaron de reportar: el agente está instalado pero no hay
 * latido reciente. Distingue "el servidor se cayó" de "el agente dejó de
 * funcionar", y avisa igual porque en ambos casos hay un problema.
 */
class ServerUnreachableRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::ServerUnreachable;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $maxAgeHours = (float) ($context->thresholds($this->key())['max_age_hours'] ?? 2);

        $servers = $context->assets->filter(
            fn (Asset $asset): bool => in_array($asset->type, [AssetType::Server, AssetType::ContainerHost], true),
        );

        foreach ($servers as $asset) {
            $hasHeartbeatCheck = $context->hasCheckOfType($asset->id, CheckType::AgentHeartbeat)
                || $context->hasCheckOfType($asset->id, CheckType::HostInfo);

            if (! $hasHeartbeatCheck) {
                continue;
            }

            $evidence = $context->latestEvidence($asset->id, CheckType::AgentHeartbeat)
                ?? $context->latestEvidence($asset->id, CheckType::HostInfo);

            if ($evidence === null) {
                return new RuleViolation(
                    rule: $this->key(),
                    severity: IncidentSeverity::Warning,
                    title: "{$asset->name} nunca ha reportado",
                    description: 'El agente está configurado pero no se ha recibido ninguna evidencia.',
                    assetId: $asset->id,
                    recommendation: 'Verificar que el agente esté instalado, con el token correcto y el servicio activo.',
                );
            }

            $hours = $context->hoursSince($evidence->collected_at);

            if ($hours <= $maxAgeHours) {
                continue;
            }

            return new RuleViolation(
                rule: $this->key(),
                severity: IncidentSeverity::Warning,
                title: sprintf('%s dejó de reportar hace %.0f horas', $asset->name, $hours),
                description: sprintf(
                    'Última evidencia recibida el %s (límite configurado: %d horas). Puede ser un problema del servidor o del agente.',
                    $evidence->collected_at->format('d/m/Y H:i'),
                    (int) $maxAgeHours,
                ),
                assetId: $asset->id,
                evidenceId: $evidence->id,
                recommendation: 'Comprobar la conectividad del servidor y el estado del servicio del agente.',
            );
        }

        return null;
    }
}
