<?php

namespace App\Services\Evidence;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Support\Timezone;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Sobre comun de evidencia.
 *
 * Toda fuente (collector, agente, webhook, registro manual) produce esta
 * estructura, de modo que exista un unico camino de entrada al sistema
 * (ver docs/evidence-model.md).
 */
final readonly class EvidencePayload
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>|null $rawData
     */
    public function __construct(
        public CheckType $type,
        public EvidenceStatus $status,
        public string $title,
        public ?string $rawStatus = null,
        public ?string $valueText = null,
        public ?float $valueNumeric = null,
        public ?string $unit = null,
        public ?array $data = null,
        public ?array $rawData = null,
        public ?DateTimeInterface $collectedAt = null,
        public EvidenceSource $source = EvidenceSource::Check,
        public string $discriminator = '',
    ) {
    }

    public static function make(
        CheckType $type,
        EvidenceStatus $status,
        string $title,
        array $options = [],
    ): self {
        return new self(
            type: $type,
            status: $status,
            title: $title,
            rawStatus: $options['raw_status'] ?? null,
            valueText: $options['value_text'] ?? null,
            valueNumeric: $options['value_numeric'] ?? null,
            unit: $options['unit'] ?? null,
            data: $options['data'] ?? null,
            rawData: $options['raw_data'] ?? null,
            collectedAt: $options['collected_at'] ?? null,
            source: $options['source'] ?? EvidenceSource::Check,
            discriminator: (string) ($options['discriminator'] ?? ''),
        );
    }

    public function collectedAtOrNow(): DateTimeInterface
    {
        return $this->collectedAt ?? new DateTimeImmutable('now', Timezone::utc());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'status' => $this->status->value,
            'raw_status' => $this->rawStatus,
            'title' => $this->title,
            'value_text' => $this->valueText,
            'value_numeric' => $this->valueNumeric,
            'unit' => $this->unit,
            'data' => $this->data,
            'raw_data' => $this->rawData,
            'source' => $this->source->value,
            'collected_at' => $this->collectedAtOrNow()->format(DateTimeInterface::ATOM),
        ];
    }
}
