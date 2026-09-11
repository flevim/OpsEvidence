<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum AssetType: string
{
    use HasValues;

    case Server = 'SERVER';
    case Website = 'WEBSITE';
    case Application = 'APPLICATION';
    case Database = 'DATABASE';
    case ContainerHost = 'CONTAINER_HOST';
    case Repository = 'REPOSITORY';
    case BackupSource = 'BACKUP_SOURCE';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Server => 'Servidor',
            self::Website => 'Sitio web',
            self::Application => 'Aplicación',
            self::Database => 'Base de datos',
            self::ContainerHost => 'Host de contenedores',
            self::Repository => 'Repositorio',
            self::BackupSource => 'Origen de backup',
            self::Other => 'Otro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Server => 'mdi-server',
            self::Website => 'mdi-web',
            self::Application => 'mdi-application-brackets-outline',
            self::Database => 'mdi-database',
            self::ContainerHost => 'mdi-docker',
            self::Repository => 'mdi-source-branch',
            self::BackupSource => 'mdi-backup-restore',
            self::Other => 'mdi-cube-outline',
        };
    }

    /**
     * Tipos de check que tienen sentido por defecto para este tipo de asset.
     *
     * @return array<int, CheckType>
     */
    public function suggestedCheckTypes(): array
    {
        return array_values(array_filter(
            CheckType::cases(),
            fn (CheckType $type): bool => $type->supportsAssetType($this),
        ));
    }
}
