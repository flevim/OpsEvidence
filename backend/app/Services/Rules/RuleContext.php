<?php

namespace App\Services\Rules;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\RuleKey;
use App\Models\Asset;
use App\Models\Check;
use App\Models\Evidence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fotografia de la infraestructura de un cliente sobre la que evaluan las reglas.
 *
 * Las reglas son funciones puras sobre este contexto: se pueden probar sin base
 * de datos.
 */
final class RuleContext
{
    /** @var array<int, RuleViolation> */
    private array $violations = [];

    /**
     * @param  Collection<int, Asset>  $assets  indexada por id
     * @param  Collection<int, Check>  $checks
     * @param  Collection<string, Evidence>  $latest  clave "{assetId}:{type}"
     */
    public function __construct(
        public readonly int $accountId,
        public readonly int $clientId,
        public readonly Collection $assets,
        public readonly Collection $checks,
        public readonly Collection $latest,
        public readonly CarbonImmutable $now,
        private readonly \Closure $thresholdResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function thresholds(RuleKey $rule): array
    {
        return ($this->thresholdResolver)($this->accountId, $this->clientId, $rule);
    }

    /**
     * @param  array<int, RuleViolation>  $violations
     */
    public function setViolations(array $violations): void
    {
        $this->violations = $violations;
    }

    /**
     * @return array<int, RuleViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function latestEvidence(int $assetId, CheckType $type): ?Evidence
    {
        return $this->latest->get($assetId.':'.$type->value);
    }

    /**
     * @return Collection<int, Evidence>
     */
    public function evidenceOfType(CheckType $type): Collection
    {
        return $this->latest
            ->filter(fn (Evidence $evidence): bool => $evidence->type === $type)
            ->values();
    }

    public function hasCheckOfType(int $assetId, CheckType $type): bool
    {
        return $this->checks->contains(
            fn (Check $check): bool => $check->asset_id === $assetId && $check->type === $type,
        );
    }

    public function asset(int $assetId): ?Asset
    {
        return $this->assets->get($assetId);
    }

    /**
     * Horas transcurridas desde un momento hasta ahora.
     *
     * Se calcula sobre timestamps y no con diffInMinutes: Carbon 3 devuelve
     * diferencias con signo, lo que invertiria las comparaciones de las reglas
     * de estancamiento.
     */
    public function hoursSince(\DateTimeInterface $moment): float
    {
        $seconds = $this->now->getTimestamp() - $moment->getTimestamp();

        return round($seconds / 3600, 1);
    }
}
