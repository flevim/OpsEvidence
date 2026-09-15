<?php

namespace App\Services\Collectors;

use App\Services\Collectors\Exceptions\CollectionFailed;
use Closure;

/**
 * Impide que los collectors HTTP/SSL alcancen loopback, redes privadas,
 * direcciones reservadas o hosts que no se puedan resolver de forma segura.
 */
final class OutboundUrlGuard
{
    /** @param (Closure(string): array<int, string>)|null $resolver */
    public function __construct(private readonly ?Closure $resolver = null) {}

    public function assertAllowed(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new CollectionFailed('La URL del check no es válida.');
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new CollectionFailed("Esquema de URL no permitido: {$scheme}");
        }

        $host = rtrim(mb_strtolower((string) parse_url($url, PHP_URL_HOST)), '.');

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new CollectionFailed('El destino del check no puede ser local.');
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw new CollectionFailed("No se pudo resolver el host del check: {$host}");
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new CollectionFailed("El destino del check resuelve a una red privada o reservada: {$address}");
            }
        }
    }

    /** @return array<int, string> */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->resolver !== null) {
            return array_values(array_unique(($this->resolver)($host)));
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
