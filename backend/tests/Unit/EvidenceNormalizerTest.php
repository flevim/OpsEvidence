<?php

use App\Domain\Enums\EvidenceStatus;
use App\Services\Evidence\EvidenceNormalizer;

/**
 * La normalizacion es una funcion pura: es la capa que traduce el dato crudo de
 * cada fuente al catalogo canonico de estados (docs/evidence-model.md).
 */
beforeEach(function () {
    $this->normalizer = new EvidenceNormalizer();
});

it('clasifica códigos de estado HTTP', function () {
    expect($this->normalizer->fromHttpStatusCode(200))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromHttpStatusCode(301))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromHttpStatusCode(404))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromHttpStatusCode(503))->toBe(EvidenceStatus::Critical);
});

it('trata la ausencia de respuesta como fallo de recolección, no como sitio caído', function () {
    expect($this->normalizer->fromHttpStatusCode(null))->toBe(EvidenceStatus::Failed);
});

it('clasifica los días restantes de un certificado SSL', function () {
    expect($this->normalizer->fromSslDaysRemaining(120))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromSslDaysRemaining(20))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromSslDaysRemaining(3))->toBe(EvidenceStatus::Critical)
        ->and($this->normalizer->fromSslDaysRemaining(-1))->toBe(EvidenceStatus::Critical)
        ->and($this->normalizer->fromSslDaysRemaining(null))->toBe(EvidenceStatus::Failed);
});

it('clasifica umbrales porcentuales configurables', function () {
    expect($this->normalizer->fromPercent(50.0, 80.0, 90.0))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromPercent(85.0, 80.0, 90.0))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromPercent(95.0, 80.0, 90.0))->toBe(EvidenceStatus::Critical)
        ->and($this->normalizer->fromPercent(null, 80.0, 90.0))->toBe(EvidenceStatus::Unknown);
});

it('entiende el vocabulario de distintas herramientas de backup', function () {
    expect($this->normalizer->fromBackupStatus('success'))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromBackupStatus('OK'))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromBackupStatus('partial'))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromBackupStatus('failed'))->toBe(EvidenceStatus::Critical)
        ->and($this->normalizer->fromBackupStatus('running'))->toBe(EvidenceStatus::Unknown)
        ->and($this->normalizer->fromBackupStatus(null))->toBe(EvidenceStatus::Unknown);
});

it('clasifica el estado de un contenedor Docker', function () {
    expect($this->normalizer->fromDockerState(true, 'healthy'))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromDockerState(true, 'starting'))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromDockerState(true, 'unhealthy'))->toBe(EvidenceStatus::Critical);
});

it('distingue un contenedor detenido con política de reinicio', function () {
    expect($this->normalizer->fromDockerState(false, null, true))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromDockerState(false, null, false))->toBe(EvidenceStatus::Critical);
});

it('clasifica la conclusión de un workflow de GitHub', function () {
    expect($this->normalizer->fromGithubConclusion('success', 'completed'))->toBe(EvidenceStatus::Healthy)
        ->and($this->normalizer->fromGithubConclusion('failure', 'completed'))->toBe(EvidenceStatus::Critical)
        ->and($this->normalizer->fromGithubConclusion('cancelled', 'completed'))->toBe(EvidenceStatus::Warning)
        ->and($this->normalizer->fromGithubConclusion(null, 'in_progress'))->toBe(EvidenceStatus::Unknown);
});

it('distingue FAILED de CRITICAL cuando la recolección falla', function () {
    // CRITICAL significa "el activo está mal"; FAILED significa "no pudimos
    // medir". Confundirlos produce informes deshonestos.
    expect($this->normalizer->fromCollectionFailure())->toBe(EvidenceStatus::Failed)
        ->and($this->normalizer->fromCollectionFailure()->isProblem())->toBeTrue();
});
