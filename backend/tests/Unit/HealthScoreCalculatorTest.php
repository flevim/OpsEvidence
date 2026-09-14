<?php

use App\Services\Reporting\HealthScoreCalculator;

beforeEach(function () {
    $this->calculator = new HealthScoreCalculator;
});

it('no devuelve ningún score cuando no hay datos de monitorización', function () {
    $result = $this->calculator->calculate([]);

    expect($result['score'])->toBeNull()
        ->and($result['band'])->toBeNull()
        ->and($result['excluded'])->not->toBeEmpty();
});

it('no premia con 100 a una cuenta sin evidencia y sin incidentes', function () {
    // Este es el error que el producto no debe cometer: cero incidentes sin
    // ninguna evidencia no significa "infraestructura perfecta".
    $result = $this->calculator->calculate([
        'incidents' => ['critical_open' => 0, 'warning_open' => 0],
    ]);

    expect($result['score'])->toBeNull();
});

it('devuelve 100 cuando todo está sano', function () {
    $result = $this->calculator->calculate([
        'availability' => ['percentage' => 100.0, 'samples' => 100],
        'backups' => ['total' => 10, 'ok' => 10, 'failed' => 0],
        'checks' => ['by_status' => ['HEALTHY' => 20, 'WARNING' => 0, 'CRITICAL' => 0]],
        'containers' => ['available' => true, 'running' => 5, 'total' => 5],
        'ssl' => ['available' => true, 'expiring' => 0, 'expired' => 0, 'total' => 2],
        'updates' => ['available' => true, 'servers_with_updates' => 0, 'total_security_updates' => 0],
        'incidents' => ['critical_open' => 0, 'warning_open' => 0],
    ]);

    expect($result['score'])->toBe(100)
        ->and($result['band'])->toBe('excellent');
});

it('castiga con dureza una infraestructura degradada', function () {
    $result = $this->calculator->calculate([
        'availability' => ['percentage' => 50.0, 'samples' => 100],
        'backups' => ['total' => 10, 'ok' => 5, 'failed' => 5],
        'checks' => ['by_status' => ['HEALTHY' => 0, 'WARNING' => 0, 'CRITICAL' => 10]],
        'ssl' => ['available' => true, 'expiring' => 0, 'expired' => 1, 'total' => 2],
        'incidents' => ['critical_open' => 2, 'warning_open' => 0],
    ]);

    expect($result['score'])->toBeLessThan(50)
        ->and($result['components']['ssl']['score'])->toBe(0.0);
});

it('excluye del cálculo los componentes sin datos en lugar de puntuarlos con cero', function () {
    $result = $this->calculator->calculate([
        'availability' => ['percentage' => 100.0, 'samples' => 10],
        'backups' => ['total' => 0, 'ok' => 0, 'failed' => 0],
        'checks' => ['by_status' => ['HEALTHY' => 5, 'WARNING' => 0, 'CRITICAL' => 0]],
        'containers' => ['available' => false],
        'ssl' => ['available' => false],
        'updates' => ['available' => false],
        'incidents' => ['critical_open' => 0, 'warning_open' => 0],
    ]);

    expect($result['score'])->toBe(100)
        ->and($result['excluded'])->toContain('backups')
        ->and($result['excluded'])->toContain('containers')
        ->and($result['excluded'])->toContain('ssl')
        ->and($result['excluded'])->toContain('updates');
});

it('informa siempre la versión de la fórmula usada', function () {
    $result = $this->calculator->calculate([]);

    expect($result['version'])->toBe('1.0');
});
