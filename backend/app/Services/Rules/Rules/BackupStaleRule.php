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
 * Detecta la ausencia de backups recientes.
 *
 * Es la regla que responde a la pregunta que ningun backup fallido responde:
 * "y si el script simplemente dejo de ejecutarse?".
 */
class BackupStaleRule implements Rule
{
    public function key(): RuleKey
    {
        return RuleKey::BackupStale;
    }

    public function evaluate(RuleContext $context): ?RuleViolation
    {
        $maxAgeHours = (float) ($context->thresholds($this->key())['max_age_hours'] ?? 36);

        $candidates = $context->assets
            ->filter(fn (Asset $asset): bool => $asset->type === AssetType::BackupSource
                || $context->hasCheckOfType($asset->id, CheckType::BackupStatus));

        foreach ($candidates as $asset) {
            $evidence = $context->latestEvidence($asset->id, CheckType::BackupStatus);

            if ($evidence === null) {
                return new RuleViolation(
                    rule: $this->key(),
                    severity: IncidentSeverity::Warning,
                    title: "No hay ningún backup registrado para {$asset->name}",
                    description: 'No se ha recibido evidencia de backups para este activo.',
                    assetId: $asset->id,
                    recommendation: 'Configurar el envío de resultados del script de backup mediante webhook.',
                );
            }

            $hours = $context->hoursSince($evidence->collected_at);

            if ($hours <= $maxAgeHours) {
                continue;
            }

            return new RuleViolation(
                rule: $this->key(),
                severity: IncidentSeverity::Critical,
                title: sprintf('El último backup de %s fue hace %.0f horas', $asset->name, $hours),
                description: sprintf(
                    'No se registra un backup exitoso desde el %s (límite configurado: %d horas).',
                    $evidence->collected_at->format('d/m/Y H:i'),
                    (int) $maxAgeHours,
                ),
                assetId: $asset->id,
                evidenceId: $evidence->id,
                recommendation: 'Verificar que el script de backup se esté ejecutando y que esté reportando el resultado.',
            );
        }

        return null;
    }
}
