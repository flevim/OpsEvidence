<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum RuleKey: string
{
    use HasValues;

    case WebsiteDown = 'WEBSITE_DOWN';
    case SslExpiring = 'SSL_EXPIRING';
    case BackupFailed = 'BACKUP_FAILED';
    case BackupStale = 'BACKUP_STALE';
    case DiskUsage = 'DISK_USAGE';
    case ContainerDown = 'CONTAINER_DOWN';
    case DockerUnhealthy = 'DOCKER_UNHEALTHY';
    case HighMemory = 'HIGH_MEMORY';
    case HighCpu = 'HIGH_CPU';
    case PendingSecurityUpdates = 'PENDING_SECURITY_UPDATES';
    case ServerUnreachable = 'SERVER_UNREACHABLE';
    case GithubWorkflowFailed = 'GITHUB_WORKFLOW_FAILED';
    case CollectorFailing = 'COLLECTOR_FAILING';

    public function label(): string
    {
        return match ($this) {
            self::WebsiteDown => 'Sitio web no disponible',
            self::SslExpiring => 'Certificado SSL por vencer',
            self::BackupFailed => 'Backup fallido',
            self::BackupStale => 'Backup desactualizado',
            self::DiskUsage => 'Uso de disco elevado',
            self::ContainerDown => 'Contenedor detenido',
            self::DockerUnhealthy => 'Contenedor no saludable',
            self::HighMemory => 'Uso de memoria elevado',
            self::HighCpu => 'Uso de CPU elevado',
            self::PendingSecurityUpdates => 'Actualizaciones de seguridad pendientes',
            self::ServerUnreachable => 'Servidor sin reportar',
            self::GithubWorkflowFailed => 'Workflow de GitHub fallido',
            self::CollectorFailing => 'La recolección está fallando',
        };
    }

    public function defaultSeverity(): IncidentSeverity
    {
        return match ($this) {
            self::WebsiteDown, self::BackupFailed, self::BackupStale,
            self::DockerUnhealthy, self::GithubWorkflowFailed => IncidentSeverity::Critical,
            self::SslExpiring, self::DiskUsage, self::ContainerDown,
            self::HighMemory, self::HighCpu, self::ServerUnreachable => IncidentSeverity::Warning,
            self::PendingSecurityUpdates, self::CollectorFailing => IncidentSeverity::Warning,
        };
    }

    /**
     * Umbrales por defecto de la regla. Configurables por cuenta o por cliente.
     *
     * @return array<string, mixed>
     */
    public function defaultThresholds(): array
    {
        return match ($this) {
            self::SslExpiring => ['warning_days' => 30, 'critical_days' => 7],
            self::DiskUsage => ['warning_percent' => 80, 'critical_percent' => 90],
            self::HighMemory => ['warning_percent' => 85, 'critical_percent' => 95],
            self::HighCpu => ['warning_percent' => 85, 'critical_percent' => 95, 'sustained_minutes' => 15],
            self::BackupStale => ['max_age_hours' => 36],
            self::ServerUnreachable => ['max_age_hours' => 2],
            self::PendingSecurityUpdates => ['warning_count' => 1, 'critical_count' => 10],
            self::CollectorFailing => ['consecutive_failures' => 3],
            default => [],
        };
    }

    public function tone(): string
    {
        return $this->defaultSeverity()->tone();
    }
}
