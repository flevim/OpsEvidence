<?php

namespace App\Services\Collectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Resultado de una sonda HTTP.
 */
final readonly class HttpProbeResult
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public bool $reachable,
        public ?int $statusCode,
        public float $totalTimeMs,
        public ?string $error = null,
        public array $headers = [],
        public ?string $finalUrl = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->reachable && $this->statusCode !== null;
    }
}

/**
 * Sonda HTTP unica reutilizada por los collectors de disponibilidad y
 * tiempo de respuesta.
 */
class HttpProbe
{
    /**
     * @param array<string, mixed> $options
     */
    public function probe(string $url, array $options = []): HttpProbeResult
    {
        $timeout = (int) config('opsevidence.collectors.http.timeout_seconds');
        $connectTimeout = (int) config('opsevidence.collectors.http.connect_timeout_seconds');

        $startedAt = microtime(true);

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'User-Agent' => config('opsevidence.collectors.http.user_agent'),
                'Accept' => '*/*',
            ])
                ->withOptions($options)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withOptions(['allow_redirects' => ['max' => (int) config('opsevidence.collectors.http.max_redirects')]])
                ->get($url);

            return new HttpProbeResult(
                reachable: true,
                statusCode: $response->status(),
                totalTimeMs: round((microtime(true) - $startedAt) * 1000, 2),
                headers: $response->headers(),
                finalUrl: $url,
            );
        } catch (ConnectionException $e) {
            return new HttpProbeResult(
                reachable: false,
                statusCode: null,
                totalTimeMs: round((microtime(true) - $startedAt) * 1000, 2),
                error: $this->summarize($e->getMessage()),
            );
        } catch (\Throwable $e) {
            return new HttpProbeResult(
                reachable: false,
                statusCode: null,
                totalTimeMs: round((microtime(true) - $startedAt) * 1000, 2),
                error: $this->summarize($e->getMessage()),
            );
        }
    }

    private function summarize(string $message): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 300);
    }
}
