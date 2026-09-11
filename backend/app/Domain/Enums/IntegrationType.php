<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum IntegrationType: string
{
    use HasValues;

    case Github = 'github';
    case Docker = 'docker';
    case UptimeKuma = 'uptime_kuma';
    case BackupWebhook = 'backup_webhook';
    case Prometheus = 'prometheus';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Github => 'GitHub',
            self::Docker => 'Docker',
            self::UptimeKuma => 'Uptime Kuma',
            self::BackupWebhook => 'Webhook de backup',
            self::Prometheus => 'Prometheus',
            self::Other => 'Otra integración',
        };
    }

    public function isImplemented(): bool
    {
        return in_array($this, [self::Github, self::BackupWebhook, self::Docker], true);
    }
}
