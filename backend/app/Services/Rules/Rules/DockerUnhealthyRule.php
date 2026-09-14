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
 * Contenedores esperados que están corriendo pero con el healthcheck en
 * "unhealthy". Un contenedor detenido a propósito con un health antiguo no
 * cuenta.
 */
class DockerUnhealthyRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::DockerUnhealthy;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $types = [CheckType::DockerContainerStatus, CheckType::DockerHealth];

        foreach ($types as $type) {
            foreach ($context->evidenceOfType($type) as $evidence) {
                $explicitExpected = is_array($evidence->data['expected'] ?? null) ? $evidence->data['expected'] : [];

                foreach ($evidence->data['containers'] ?? [] as $container) {
                    if (! is_array($container)) {
                        continue;
                    }

                    if (mb_strtolower((string) ($container['health'] ?? '')) !== 'unhealthy') {
                        continue;
                    }

                    if (! ContainerExpectation::isExpected($container, $explicitExpected)) {
                        continue;
                    }

                    if (! ContainerExpectation::isRunning($container)) {
                        continue;
                    }

                    $name = (string) ($container['name'] ?? 'sin nombre');
                    $assetName = $context->asset($evidence->asset_id)?->name ?? 'el host';

                    return new RuleViolation(
                        rule: $this->key(),
                        severity: IncidentSeverity::Critical,
                        title: "El contenedor {$name} está marcado como no saludable",
                        description: sprintf('En %s, el healthcheck de "%s" reporta "unhealthy".', $assetName, $name),
                        assetId: $evidence->asset_id,
                        evidenceId: $evidence->id,
                        recommendation: 'Revisar la salud del servicio dentro del contenedor y sus dependencias.',
                    );
                }
            }
        }

        return null;
    }
}
