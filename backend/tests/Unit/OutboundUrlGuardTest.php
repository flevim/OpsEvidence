<?php

use App\Services\Collectors\Exceptions\CollectionFailed;
use App\Services\Collectors\HttpProbe;
use App\Services\Collectors\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;

it('permite hosts que resuelven exclusivamente a direcciones públicas', function () {
    $guard = new OutboundUrlGuard(fn (string $host): array => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']);

    expect(fn () => $guard->assertAllowed('https://example.com/status'))->not->toThrow(CollectionFailed::class);
});

it('rechaza loopback y redes privadas o reservadas', function (string $url) {
    $guard = new OutboundUrlGuard;

    expect(fn () => $guard->assertAllowed($url))->toThrow(CollectionFailed::class);
})->with([
    'localhost' => 'http://localhost/admin',
    'loopback IPv4' => 'http://127.0.0.1/admin',
    'red privada clase A' => 'http://10.0.0.1/',
    'red privada clase B' => 'http://172.16.0.1/',
    'red privada clase C' => 'http://192.168.1.1/',
    'metadata cloud' => 'http://169.254.169.254/latest/meta-data',
    'loopback IPv6' => 'http://[::1]/',
]);

it('rechaza un hostname público que resuelve a una dirección privada', function () {
    $guard = new OutboundUrlGuard(fn (string $host): array => ['10.0.0.8']);

    expect(fn () => $guard->assertAllowed('https://intranet.example.com'))->toThrow(CollectionFailed::class);
});

it('falla cerrado cuando el hostname no se puede resolver', function () {
    $guard = new OutboundUrlGuard(fn (string $host): array => []);

    expect(fn () => $guard->assertAllowed('https://sin-dns.example'))->toThrow(CollectionFailed::class);
});

it('no sigue una redirección desde un destino público hacia una red privada', function () {
    Http::fake([
        'https://public.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);

    $guard = new OutboundUrlGuard(fn (string $host): array => ['93.184.216.34']);
    $probe = new HttpProbe($guard);

    expect(fn () => $probe->probe('https://public.example/start'))->toThrow(CollectionFailed::class);
    Http::assertSentCount(1);
});
