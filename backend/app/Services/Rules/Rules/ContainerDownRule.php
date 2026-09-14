<?php

namespace App\Services\Rules\Rules;

use App\Domain\Containers\ContainerExpectation;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * Contenedores que deberían estar corriendo y no lo están.
 *
 * Solo se evalúan los contenedores *esperados*: los que tienen política de
 * reinicio (always/unless-stopped/on-failure) o los declarados explícitamente
 * en la configuración del check. Un contenedor detenido a propósito no es una
 * incidencia.
 *
 * Motivo: en el primer servidor real, 22 de 48 contenedores estaban detenidos
 * a propósito y la regla los marcaba todos como caídos.
 */
class ContainerDownRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::ContainerDown;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        foreach ($context->evidenceOfType(CheckType::DockerContainerStatus) as $evidence) {
            $containers = $evidence->data['containers'] ?? [];
            $explicitExpected = is_array($evidence->data['expected'] ?? null) ? $evidence->data['expected'] : [];

            foreach ($containers as $container) {
                if (! is_array($container)) {
                    continue;
                }

                if (! ContainerExpectation::isExpected($container, $explicitExpected)) {
                    continue;
                }

                if (ContainerExpectation::isRunning($container)) {
                    continue;
                }

                $name = (string) ($container['name'] ?? 'sin nombre');
                $state = (string) ($container['state'] ?? 'desconocido');
                $policy = (string) ($container['restart_policy'] ?? 'no');
                $restarts = (int) ($container['restart_count'] ?? 0);
                $assetName = $context->asset($evidence->asset_id)?->name ?? 'el host';

                $description = sprintf(
                    'En %s, el contenedor "%s" está en estado "%s" teniendo política de reinicio "%s".',
                    $assetName,
                    $name,
                    $state !== '' ? $state : 'desconocido',
                    $policy,
                );

                if ($restarts > 0) {
                    $description .= sprintf(' Acumula %d reinicios.', $restarts);
                }

                return new RuleViolation(
                    rule: $this->key(),
                    severity: IncidentSeverity::Critical,
                    title: "El contenedor {$name} no está en ejecución",
                    description: $description,
                    assetId: $evidence->asset_id,
                    evidenceId: $evidence->id,
                    recommendation: 'Revisar los logs del contenedor y reiniciarlo si corresponde.',
                );
            }
        }

        return null;
    }
}
