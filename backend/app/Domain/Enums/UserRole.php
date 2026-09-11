<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case Owner = 'owner';
    case Admin = 'admin';
    case Technician = 'technician';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propietario',
            self::Admin => 'Administrador',
            self::Technician => 'Técnico',
            self::Viewer => 'Solo lectura',
        };
    }

    /**
     * Nivel jerárquico: a mayor número, más privilegios.
     */
    public function level(): int
    {
        return match ($this) {
            self::Viewer => 10,
            self::Technician => 20,
            self::Admin => 30,
            self::Owner => 40,
        };
    }

    public function canWrite(): bool
    {
        return $this->level() >= self::Technician->level();
    }

    public function canManageClients(): bool
    {
        return $this->level() >= self::Admin->level();
    }

    public function canManageUsers(): bool
    {
        return $this->level() >= self::Admin->level();
    }

    public function canManageTokens(): bool
    {
        return $this->level() >= self::Admin->level();
    }

    public function canDeleteAccount(): bool
    {
        return $this === self::Owner;
    }

    /**
     * @return array<int, string>
     */
    public static function assignable(): array
    {
        return self::values();
    }
}
