<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum ActivityType: string
{
    use HasValues;

    case Maintenance = 'maintenance';
    case Upgrade = 'upgrade';
    case SslRenewal = 'ssl_renewal';
    case Restore = 'restore';
    case Deploy = 'deploy';
    case ConfigChange = 'config_change';
    case Restart = 'restart';
    case Monitoring = 'monitoring';
    case IncidentResponse = 'incident_response';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Mantenimiento',
            self::Upgrade => 'Actualización',
            self::SslRenewal => 'Renovación de SSL',
            self::Restore => 'Restauración de backup',
            self::Deploy => 'Despliegue',
            self::ConfigChange => 'Cambio de configuración',
            self::Restart => 'Reinicio controlado',
            self::Monitoring => 'Revisión de monitorización',
            self::IncidentResponse => 'Respuesta a incidente',
            self::Other => 'Otra actividad',
        };
    }
}
