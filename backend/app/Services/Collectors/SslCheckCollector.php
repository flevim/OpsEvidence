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
 * Vencimiento del certificado TLS.
 *
 * Solo realiza el handshake TLS: no descarga la pagina. Es mas rapido y menos
 * intrusivo que una peticion HTTP completa.
 */
class SslCheckCollector implements Collector
{
    public function __construct(
        private readonly EvidenceNormalizer $normalizer,
    ) {
    }

    public function supports(): array
    {
        return [CheckType::SslExpiration];
    }

    public function collect(Check $check): array
    {
        [$host, $port] = $this->resolveTarget($check);

        $thresholds = $check->configuration ?? [];
        $warningDays = (int) ($thresholds['warning_days'] ?? 30);
        $criticalDays = (int) ($thresholds['critical_days'] ?? 7);

        $certificate = $this->fetchCertificate($host, $port);

        if ($certificate === null) {
            return [
                EvidencePayload::make(
                    type: CheckType::SslExpiration,
                    status: EvidenceStatus::Failed,
                    title: 'No se pudo leer el certificado SSL',
                    options: [
                        'raw_status' => 'handshake_failed',
                        'data' => ['host' => $host, 'port' => $port],
                        'raw_data' => ['host' => $host, 'port' => $port],
                        'discriminator' => $host.':'.$port,
                    ],
                ),
            ];
        }

        $daysRemaining = $certificate['days_remaining'];
        $status = $this->normalizer->fromSslDaysRemaining($daysRemaining, $warningDays, $criticalDays);

        return [
            EvidencePayload::make(
                type: CheckType::SslExpiration,
                status: $status,
                title: $this->title($daysRemaining, $certificate['valid_to']),
                options: [
                    'raw_status' => (string) $daysRemaining,
                    'value_numeric' => $daysRemaining,
                    'unit' => 'days',
                    'data' => [
                        'host' => $host,
                        'port' => $port,
                        'days_remaining' => $daysRemaining,
                        'valid_from' => $certificate['valid_from'],
                        'valid_to' => $certificate['valid_to'],
                        'issuer' => $certificate['issuer'],
                        'subject' => $certificate['subject'],
                    ],
                    'raw_data' => $certificate['raw'],
                    'discriminator' => $host.':'.$port,
                ],
            ),
        ];
    }

    /**
     * @return array{
     *     days_remaining: int,
     *     valid_from: string,
     *     valid_to: string,
     *     issuer: string|null,
     *     subject: string|null,
     *     raw: array<string, mixed>
     * }|null
     */
    private function fetchCertificate(string $host, int $port): ?array
    {
        $timeout = (int) config('opsevidence.collectors.ssl.timeout_seconds');

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $client = @stream_socket_client(
            sprintf('ssl://%s:%d', $host, $port),
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return null;
        }

        try {
            $params = stream_context_get_params($client);
            $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

            if ($certificate === null) {
                return null;
            }

            $parsed = openssl_x509_parse($certificate);

            if ($parsed === false || ! isset($parsed['validTo_time_t'])) {
                return null;
            }

            $validTo = (int) $parsed['validTo_time_t'];

            return [
                'days_remaining' => (int) floor(($validTo - time()) / 86400),
                'valid_from' => date(DATE_ATOM, (int) ($parsed['validFrom_time_t'] ?? 0)),
                'valid_to' => date(DATE_ATOM, $validTo),
                'issuer' => $parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? null,
                'subject' => $parsed['subject']['CN'] ?? null,
                'raw' => [
                    'issuer' => $parsed['issuer'] ?? null,
                    'subject' => $parsed['subject'] ?? null,
                    'valid_from' => $parsed['validFrom'] ?? null,
                    'valid_to' => $parsed['validTo'] ?? null,
                    'serial_number' => $parsed['serialNumber'] ?? null,
                ],
            ];
        } finally {
            if (is_resource($client)) {
                fclose($client);
            }
        }
    }

    private function title(int $daysRemaining, string $validTo): string
    {
        if ($daysRemaining < 0) {
            return 'El certificado SSL está vencido';
        }

        if ($daysRemaining === 0) {
            return 'El certificado SSL vence hoy';
        }

        return sprintf(
            'El certificado SSL vence en %d %s (%s)',
            $daysRemaining,
            $daysRemaining === 1 ? 'día' : 'días',
            date('d/m/Y', strtotime($validTo)),
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveTarget(Check $check): array
    {
        $url = $check->configuration['url']
            ?? $check->asset?->address
            ?? $check->asset?->hostname;

        if (blank($url)) {
            throw new CollectionFailed('El check SSL no tiene URL ni el asset tiene dirección.');
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (blank($host)) {
            throw new CollectionFailed("No se pudo extraer el host de la URL: {$url}");
        }

        $port = (int) (parse_url($url, PHP_URL_PORT) ?? 443);

        return [$host, $port];
    }
}
