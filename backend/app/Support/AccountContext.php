<?php

namespace App\Support;

use Closure;

/**
 * Contexto de tenant activo para la peticion o el job en curso.
 *
 * Es la fuente del global scope de aislamiento (ver docs/security.md).
 * Se establece en el middleware de API autenticada y de forma explicita en
 * los jobs, comandos y endpoints de agente/webhook.
 */
class AccountContext
{
    private static ?int $accountId = null;

    public static function set(?int $accountId): void
    {
        self::$accountId = $accountId;
    }

    public static function current(): ?int
    {
        return self::$accountId;
    }

    public static function has(): bool
    {
        return self::$accountId !== null;
    }

    public static function forget(): void
    {
        self::$accountId = null;
    }

    /**
     * Ejecuta un bloque con un tenant fijo y restaura el contexto anterior.
     */
    public static function run(?int $accountId, Closure $callback): mixed
    {
        $previous = self::$accountId;
        self::set($accountId);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
