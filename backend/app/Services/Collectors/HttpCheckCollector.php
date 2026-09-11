<?php

namespace App\Services\Collectors;

use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Models\Check;
use App\Services\Collectors\Contracts\Collector;
use App\Services\Collectors\Exceptions\CollectionFailed;
use App\Services\Evidence\EvidenceNormalizer;
use App\Services\Evidence\EvidencePayload;

/**
 * Disponibilidad y tiempo de respuesta HTTP.
 */
class HttpCheckCollector implements Collector
{
    public function __construct(
        private readonly HttpProbe $probe,
        private readonly EvidenceNormalizer $normalizer,
    ) {
    }

    public function supports(): array
    {
        return [CheckType::HttpStatus, CheckType::HttpResponseTime];
    }

    public function collect(Check $check): array
    {
        $url = $this->resolveUrl($check);

        $result = $this->probe->probe($url, ['http_errors' => false]);

        return match ($check->type) {
            CheckType::HttpStatus => [$this->statusPayload($check, $url, $result)],
            CheckType::HttpResponseTime => [$this->responseTimePayload($check, $url, $result)],
            default => throw new CollectionFailed("Tipo de check no soportado por HttpCheckCollector: {$check->type->value}"),
        };
    }

    private function statusPayload(Check $check, string $url, HttpProbeResult $result): EvidencePayload
    {
        if (! $result->reachable) {
            return EvidencePayload::make(
                type: CheckType::HttpStatus,
                status: EvidenceStatus::Critical,
                title: 'El sitio no responde',
                options: [
                    'raw_status' => 'unreachable',
                    'value_text' => 'unreachable',
                    'data' => ['url' => $url, 'error' => $result->error],
                    'raw_data' => ['url' => $url, 'error' => $result->error],
                    'discriminator' => $url,
                ],
            );
        }

        $status = $this->normalizer->fromHttpStatusCode($result->statusCode);

        return EvidencePayload::make(
            type: CheckType::HttpStatus,
            status: $status,
            title: $this->titleForStatus($result->statusCode),
            options: [
                'raw_status' => (string) $result->statusCode,
                'value_text' => (string) $result->statusCode,
                'value_numeric' => $result->statusCode,
                'unit' => 'status_code',
                'data' => [
                    'url' => $url,
                    'status_code' => $result->statusCode,
                    'response_time_ms' => $result->totalTimeMs,
                ],
                'raw_data' => [
                    'url' => $url,
                    'status_code' => $result->statusCode,
                    'response_time_ms' => $result->totalTimeMs,
                ],
                'discriminator' => $url,
            ],
        );
    }

    private function responseTimePayload(Check $check, string $url, HttpProbeResult $result): EvidencePayload
    {
        $thresholds = $check->configuration ?? [];
        $warning = (float) ($thresholds['warning_ms'] ?? 1500);
        $critical = (float) ($thresholds['critical_ms'] ?? 4000);

        if (! $result->reachable) {
            return EvidencePayload::make(
                type: CheckType::HttpResponseTime,
                status: EvidenceStatus::Critical,
                title: 'No se pudo medir el tiempo de respuesta',
                options: [
                    'raw_status' => 'unreachable',
                    'data' => ['url' => $url, 'error' => $result->error],
                    'raw_data' => ['url' => $url, 'error' => $result->error],
                    'discriminator' => $url,
                ],
            );
        }

        return EvidencePayload::make(
            type: CheckType::HttpResponseTime,
            status: $this->severityForLatency($result->totalTimeMs, $warning, $critical),
            title: sprintf('Tiempo de respuesta: %s ms', number_format($result->totalTimeMs, 0, ',', '.')),
            options: [
                'raw_status' => (string) $result->totalTimeMs,
                'value_numeric' => $result->totalTimeMs,
                'unit' => 'ms',
                'data' => [
                    'url' => $url,
                    'response_time_ms' => $result->totalTimeMs,
                    'status_code' => $result->statusCode,
                ],
                'raw_data' => [
                    'url' => $url,
                    'response_time_ms' => $result->totalTimeMs,
                ],
                'discriminator' => $url,
            ],
        );
    }

    private function severityForLatency(float $ms, float $warning, float $critical): EvidenceStatus
    {
        return match (true) {
            $ms >= $critical => EvidenceStatus::Critical,
            $ms >= $warning => EvidenceStatus::Warning,
            default => EvidenceStatus::Healthy,
        };
    }

    private function titleForStatus(?int $statusCode): string
    {
        return match (true) {
            $statusCode === null => 'Sin respuesta',
            $statusCode >= 200 && $statusCode < 300 => 'El sitio responde correctamente',
            $statusCode >= 300 && $statusCode < 400 => 'El sitio responde con redirección',
            $statusCode >= 400 && $statusCode < 500 => 'El sitio responde con error de cliente',
            $statusCode >= 500 => 'El sitio responde con error de servidor',
            default => 'Respuesta inesperada',
        };
    }

    private function resolveUrl(Check $check): string
    {
        $url = $check->configuration['url'] ?? null;

        if (blank($url)) {
            $url = $check->asset?->address ?? $check->asset?->hostname;
        }

        if (blank($url)) {
            throw new CollectionFailed('El check HTTP no tiene URL configurada ni el asset tiene dirección.');
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new CollectionFailed("Esquema de URL no permitido: {$scheme}");
        }

        return $url;
    }
}
