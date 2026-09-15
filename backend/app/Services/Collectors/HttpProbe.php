<?php

namespace App\Services\Collectors;

use App\Services\Collectors\Exceptions\CollectionFailed;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Resultado de una sonda HTTP.
 */
final readonly class HttpProbeResult
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public bool $reachable,
        public ?int $statusCode,
        public float $totalTimeMs,
        public ?string $error = null,
        public array $headers = [],
        public ?string $finalUrl = null,
    ) {}

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
    public function __construct(private readonly OutboundUrlGuard $urlGuard) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function probe(string $url, array $options = []): HttpProbeResult
    {
        $timeout = (int) config('opsevidence.collectors.http.timeout_seconds');
        $connectTimeout = (int) config('opsevidence.collectors.http.connect_timeout_seconds');

        $startedAt = microtime(true);

        try {
            [$response, $finalUrl] = $this->requestFollowingSafeRedirects(
                $url,
                $options,
                $timeout,
                $connectTimeout,
            );

            return new HttpProbeResult(
                reachable: true,
                statusCode: $response->status(),
                totalTimeMs: round((microtime(true) - $startedAt) * 1000, 2),
                headers: $response->headers(),
                finalUrl: $finalUrl,
            );
        } catch (CollectionFailed $e) {
            throw $e;
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

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: Response, 1: string}
     */
    private function requestFollowingSafeRedirects(
        string $url,
        array $options,
        int $timeout,
        int $connectTimeout,
    ): array {
        $currentUrl = $url;
        $maxRedirects = (int) config('opsevidence.collectors.http.max_redirects');

        for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
            $this->urlGuard->assertAllowed($currentUrl);

            /** @var Response $response */
            $response = Http::withHeaders([
                'User-Agent' => config('opsevidence.collectors.http.user_agent'),
                'Accept' => '*/*',
            ])
                ->withOptions($options)
                ->withOptions(['allow_redirects' => false])
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->get($currentUrl);

            $location = $response->header('Location');

            if (! $response->redirect() || blank($location)) {
                return [$response, $currentUrl];
            }

            if ($redirects === $maxRedirects) {
                throw new CollectionFailed("El check superó el máximo de {$maxRedirects} redirecciones.");
            }

            $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
        }

        throw new CollectionFailed('No se pudo completar la redirección del check.');
    }

    private function summarize(string $message): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 300);
    }
}
