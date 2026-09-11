<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * Contenedores en ejecución cuyo healthcheck de Docker reporta "unhealthy".
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
                foreach ($evidence->data['containers'] ?? [] as $container) {
                    if (mb_strtolower((string) ($container['health'] ?? '')) !== 'unhealthy') {
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
