<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum CheckType: string
{
    use HasValues;

    case HttpStatus = 'HTTP_STATUS';
    case HttpResponseTime = 'HTTP_RESPONSE_TIME';
    case SslExpiration = 'SSL_EXPIRATION';
    case ServerUptime = 'SERVER_UPTIME';
    case CpuUsage = 'CPU_USAGE';
    case MemoryUsage = 'MEMORY_USAGE';
    case DiskUsage = 'DISK_USAGE';
    case PendingUpdates = 'PENDING_UPDATES';
    case DockerContainerStatus = 'DOCKER_CONTAINER_STATUS';
    case DockerHealth = 'DOCKER_HEALTH';
    case BackupStatus = 'BACKUP_STATUS';
    case GithubWorkflow = 'GITHUB_WORKFLOW';
    case HostInfo = 'HOST_INFO';
    case AgentHeartbeat = 'AGENT_HEARTBEAT';

    public function label(): string
    {
        return match ($this) {
            self::HttpStatus => 'Disponibilidad HTTP',
            self::HttpResponseTime => 'Tiempo de respuesta HTTP',
            self::SslExpiration => 'Vencimiento de certificado SSL',
            self::ServerUptime => 'Tiempo encendido del servidor',
            self::CpuUsage => 'Uso de CPU',
            self::MemoryUsage => 'Uso de memoria',
            self::DiskUsage => 'Uso de disco',
            self::PendingUpdates => 'Actualizaciones pendientes',
            self::DockerContainerStatus => 'Estado de contenedores',
            self::DockerHealth => 'Salud de contenedores',
            self::BackupStatus => 'Estado de backups',
            self::GithubWorkflow => 'Workflows de GitHub',
            self::HostInfo => 'Información del host',
            self::AgentHeartbeat => 'Latido del agente',
        };
    }

    /**
     * Intervalo de recolección por defecto, en segundos.
     */
    public function defaultIntervalSeconds(): int
    {
        return match ($this) {
            self::HttpStatus, self::HttpResponseTime => 300,
            self::SslExpiration => 43200,
            self::AgentHeartbeat => 300,
            self::HostInfo => 86400,
            self::GithubWorkflow => 900,
            default => 600,
        };
    }

    /**
     * Segundos tras los cuales la evidencia de este check se considera stale.
     */
    public function defaultFreshnessTtlSeconds(): int
    {
        return max($this->defaultIntervalSeconds() * 3, 900);
    }

    /**
     * Si OpsEvidence ejecuta este check por sí mismo.
     * Los que no, reciben la evidencia del agente o de un webhook.
     */
    public function isCollectedByPlatform(): bool
    {
        return in_array($this, [
            self::HttpStatus,
            self::HttpResponseTime,
            self::SslExpiration,
            self::GithubWorkflow,
        ], true);
    }

    /**
     * @return array<int, AssetType>
     */
    public function supportedAssetTypes(): array
    {
        return match ($this) {
            self::HttpStatus, self::HttpResponseTime, self::SslExpiration => [
                AssetType::Website,
                AssetType::Application,
            ],
            self::ServerUptime, self::CpuUsage, self::MemoryUsage, self::DiskUsage,
            self::PendingUpdates, self::HostInfo, self::AgentHeartbeat => [
                AssetType::Server,
                AssetType::ContainerHost,
            ],
            self::DockerContainerStatus, self::DockerHealth => [
                AssetType::ContainerHost,
                AssetType::Server,
            ],
            self::BackupStatus => [
                AssetType::BackupSource,
                AssetType::Database,
                AssetType::Server,
            ],
            self::GithubWorkflow => [
                AssetType::Repository,
            ],
        };
    }

    public function supportsAssetType(AssetType $assetType): bool
    {
        return in_array($assetType, $this->supportedAssetTypes(), true);
    }
}
