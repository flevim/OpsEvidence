<?php

namespace App\Services\Rules\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\IncidentSeverity;
use App\Domain\Enums\RuleKey;
use App\Services\Rules\Contracts\Rule;
use App\Services\Rules\RuleContext;
use App\Services\Rules\RuleViolation;

/**
 * Contenedores que deberían estar corriendo y no lo están.
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
            $expected = $evidence->data['expected'] ?? [];

            foreach ($containers as $container) {
                $state = mb_strtolower((string) ($container['state'] ?? ''));
                $name = (string) ($container['name'] ?? 'sin nombre');

                if ($state === 'running' || $state === 'restarting') {
                    continue;
                }

                if ($expected !== [] && ! in_array($name, $expected, true)) {
                    continue;
                }

                $assetName = $context->asset($evidence->asset_id)?->name ?? 'el host';

                return new RuleViolation(
                    rule: $this->key(),
                    severity: IncidentSeverity::Critical,
                    title: "El contenedor {$name} no está en ejecución",
                    description: sprintf('En %s, el contenedor "%s" está en estado "%s".', $assetName, $name, $state !== '' ? $state : 'desconocido'),
                    assetId: $evidence->asset_id,
                    evidenceId: $evidence->id,
                    recommendation: 'Revisar los logs del contenedor y reiniciarlo si corresponde.',
                );
            }
        }

        return null;
    }
}
