<?php

use App\Domain\Containers\ContainerExpectation;
use App\Domain\Enums\CheckType;
use App\Domain\Enums\EvidenceStatus;
use App\Services\Agent\AgentEvidenceNormalizer;

it('considera esperado un contenedor con política always', function () {
    expect(ContainerExpectation::isExpected(['name' => 'web', 'restart_policy' => 'always']))->toBeTrue()
        ->and(ContainerExpectation::isExpected(['name' => 'web', 'restart_policy' => 'ALWAYS']))->toBeTrue();
});

it('no considera esperados los detenidos con unless-stopped u on-failure', function () {
    // `unless-stopped` significa "reinicia salvo que lo hayan detenido": si está
    // detenido, alguien lo paró a propósito y no es una incidencia. Compose pone
    // esa política por defecto, así que contarla llenaba el informe de ruido.
    expect(ContainerExpectation::isExpected(['name' => 'viejo', 'restart_policy' => 'unless-stopped']))->toBeFalse()
        ->and(ContainerExpectation::isExpected(['name' => 'tarea', 'restart_policy' => 'on-failure']))->toBeFalse();
});

it('no considera esperado un contenedor sin política de reinicio', function () {
    // Un contenedor de una sola ejecución que terminó no es una incidencia.
    expect(ContainerExpectation::isExpected(['name' => 'migracion', 'restart_policy' => 'no']))->toBeFalse()
        ->and(ContainerExpectation::isExpected(['name' => 'viejo']))->toBeFalse()
        ->and(ContainerExpectation::isExpected(['name' => 'raro', 'restart_policy' => '']))->toBeFalse();
});

it('respeta la lista explícita de contenedores esperados', function () {
    $container = ['name' => 'backup-nocturno', 'restart_policy' => 'no'];

    expect(ContainerExpectation::isExpected($container))->toBeFalse()
        ->and(ContainerExpectation::isExpected($container, ['backup-nocturno']))->toBeTrue();
});

it('la lista explícita se suma a la política, no la reemplaza', function () {
    $withPolicy = ['name' => 'web', 'restart_policy' => 'always'];

    expect(ContainerExpectation::isExpected($withPolicy, ['otro']))->toBeTrue();
});

it('reconoce running y restarting como en ejecución', function () {
    expect(ContainerExpectation::isRunning(['state' => 'running']))->toBeTrue()
        ->and(ContainerExpectation::isRunning(['state' => 'RESTARTING']))->toBeTrue()
        ->and(ContainerExpectation::isRunning(['state' => 'exited']))->toBeFalse()
        ->and(ContainerExpectation::isRunning(['state' => 'created']))->toBeFalse();
});

it('la normalización solo se altera por contenedores esperados', function () {
    $normalizer = app(AgentEvidenceNormalizer::class);

    // 2 con `always` corriendo + 3 detenidos con `unless-stopped`: no es un problema.
    $healthy = $normalizer->normalize(CheckType::DockerContainerStatus, [
        'containers' => [
            ['name' => 'web', 'state' => 'running', 'health' => 'healthy', 'restart_policy' => 'always'],
            ['name' => 'db', 'state' => 'running', 'health' => 'healthy', 'restart_policy' => 'always'],
            ['name' => 'a', 'state' => 'exited', 'restart_policy' => 'unless-stopped'],
            ['name' => 'b', 'state' => 'exited', 'restart_policy' => 'unless-stopped'],
            ['name' => 'c', 'state' => 'exited', 'restart_policy' => 'no'],
        ],
    ]);

    expect($healthy->status)->toBe(EvidenceStatus::Healthy)
        ->and($healthy->title)->toContain('2 en ejecución de 5');

    // Un contenedor con `always` detenido sí es crítico.
    $critical = $normalizer->normalize(CheckType::DockerContainerStatus, [
        'containers' => [
            ['name' => 'web', 'state' => 'running', 'health' => 'healthy', 'restart_policy' => 'always'],
            ['name' => 'api', 'state' => 'exited', 'restart_policy' => 'always'],
        ],
    ]);

    expect($critical->status)->toBe(EvidenceStatus::Critical)
        ->and($critical->title)->toContain('1 de ellos esperados y detenidos');
});
