<?php

namespace App\Services\Agent;

use App\Domain\Containers\ContainerExpectation;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceSource;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Check;
use App\Services\Evidence\EvidenceNormalizer;
use App\Services\Evidence\EvidencePayload;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Normaliza la evidencia que envia el agente.
 *
 * El agente NO decide el estado: solo reporta datos crudos. La clasificacion
 * ocurre aqui, en el servidor, con los umbrales configurados en el check.
 * Asi, un token comprometido no puede inyectar un "todo esta bien" falso
 * (ver docs/security.md, amenaza T16).
 */
class AgentEvidenceNormalizer
{
    public function __construct(private readonly EvidenceNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function normalize(
        CheckType $type,
        array $data,
        ?Check $check = null,
        ?string $rawStatus = null,
        ?string $title = null,
        ?CarbonImmutable $collectedAt = null,
    ): EvidencePayload {
        // La lista de contenedores esperados vive en la configuracion del check.
        // Se copia al dato para que el estado y las reglas la lean sin depender
        // del check.
        if (in_array($type, [CheckType::DockerContainerStatus, CheckType::DockerHealth], true)) {
            $expected = $check?->configuration['expected'] ?? null;

            if (is_array($expected) && $expected !== []) {
                $data['expected'] = array_values($expected);
            }
        }

        [$status, $autoTitle, $value, $unit] = $this->classify($type, $data, $check);

        return EvidencePayload::make(
            type: $type,
            status: $status,
            title: $title ?? $autoTitle,
            options: [
                'raw_status' => $rawStatus,
                'value_text' => $data['value_text'] ?? null,
                'value_numeric' => $value,
                'unit' => $unit,
                'data' => $data,
                'raw_data' => $data,
                'source' => EvidenceSource::Agent,
                'collected_at' => $collectedAt,
                'discriminator' => $this->discriminator($type, $data),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: EvidenceStatus, 1: string, 2: float|null, 3: string|null}
     */
    private function classify(CheckType $type, array $data, ?Check $check): array
    {
        $configuration = $check?->configuration ?? [];

        return match ($type) {
            CheckType::HostInfo => [
                EvidenceStatus::Healthy,
                'Información del servidor: '.($data['os'] ?? 'sistema desconocido'),
                null,
                null,
            ],
            CheckType::AgentHeartbeat => [
                EvidenceStatus::Healthy,
                'El agente está reportando',
                null,
                null,
            ],
            CheckType::ServerUptime => [
                $this->normalizer->fromUptimeSeconds($this->intOrNull($data['uptime_seconds'] ?? null)),
                'El servidor está encendido',
                $this->floatOrNull($data['uptime_seconds'] ?? null),
                'seconds',
            ],
            CheckType::CpuUsage => $this->percent(
                $data,
                $configuration['warning_percent'] ?? 85,
                $configuration['critical_percent'] ?? 95,
                'Uso de CPU',
                'percent',
            ),
            CheckType::MemoryUsage => $this->percent(
                $data,
                $configuration['warning_percent'] ?? 85,
                $configuration['critical_percent'] ?? 95,
                'Uso de memoria',
                'used_percent',
            ),
            CheckType::DiskUsage => $this->percent(
                $data,
                $configuration['warning_percent'] ?? 80,
                $configuration['critical_percent'] ?? 90,
                'Uso de disco',
                'used_percent',
            ),
            CheckType::PendingUpdates => $this->pendingUpdates($data),
            CheckType::DockerContainerStatus, CheckType::DockerHealth => $this->docker($type, $data),
            default => [EvidenceStatus::Unknown, $type->label(), null, null],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: EvidenceStatus, 1: string, 2: float|null, 3: string|null}
     */
    private function percent(array $data, float|int $warning, float|int $critical, string $label, string $key): array
    {
        $percent = $this->floatOrNull($data[$key] ?? null);

        return [
            $this->normalizer->fromPercent($percent, (float) $warning, (float) $critical),
            $percent === null
                ? "{$label}: sin datos"
                : sprintf('%s: %s %%', $label, number_format($percent, 1, ',', '.')),
            $percent,
            '%',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: EvidenceStatus, 1: string, 2: float|null, 3: string|null}
     */
    private function pendingUpdates(array $data): array
    {
        $security = (int) ($data['security_count'] ?? 0);
        $total = (int) ($data['total_count'] ?? $security);

        return [
            $this->normalizer->fromSecurityUpdates($security),
            $security > 0
                ? "Actualizaciones pendientes: {$security} de seguridad de {$total} en total"
                : 'Sin actualizaciones de seguridad pendientes',
            (float) $security,
            'count',
        ];
    }

    /**
     * Un solo registro resume el estado de todos los contenedores; el detalle
     * viaja en `data.containers` y es lo que leen las reglas.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: EvidenceStatus, 1: string, 2: float|null, 3: string|null}
     */
    private function docker(CheckType $type, array $data): array
    {
        $containers = is_array($data['containers'] ?? null) ? $data['containers'] : [];
        $explicitExpected = is_array($data['expected'] ?? null) ? $data['expected'] : [];

        $running = 0;

        foreach ($containers as $container) {
            if (is_array($container) && ContainerExpectation::isRunning($container)) {
                $running++;
            }
        }

        // Solo se evaluan los contenedores que DEBERIAN estar corriendo. Los
        // detenidos a proposito no son un problema (ver ContainerExpectation).
        $expectedContainers = ContainerExpectation::expected(
            array_values(array_filter($containers, 'is_array')),
            $explicitExpected,
        );

        $stoppedExpected = 0;
        $unhealthy = 0;
        $starting = 0;

        foreach ($expectedContainers as $container) {
            $isRunning = ContainerExpectation::isRunning($container);
            $health = mb_strtolower((string) ($container['health'] ?? ''));

            if (! $isRunning) {
                $stoppedExpected++;
            }

            if ($isRunning && $health === 'unhealthy') {
                $unhealthy++;
            } elseif ($health === 'starting') {
                $starting++;
            }
        }

        $status = match (true) {
            $containers === [] => EvidenceStatus::Unknown,
            $unhealthy > 0, $stoppedExpected > 0 => EvidenceStatus::Critical,
            $starting > 0 => EvidenceStatus::Warning,
            default => EvidenceStatus::Healthy,
        };

        $label = $type === CheckType::DockerHealth ? 'Salud de contenedores' : 'Contenedores';
        $detail = $stoppedExpected > 0 ? ", {$stoppedExpected} de ellos esperados y detenidos" : '';

        return [
            $status,
            sprintf('%s: %d en ejecución de %d%s', $label, $running, count($containers), $detail),
            (float) $running,
            'count',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function discriminator(CheckType $type, array $data): string
    {
        return match ($type) {
            CheckType::DiskUsage => (string) ($data['mountpoint'] ?? ''),
            default => '',
        };
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        try {
            return (float) $value;
        } catch (Throwable) {
            return null;
        }
    }
}
