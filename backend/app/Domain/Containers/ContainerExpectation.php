<?php

namespace App\Domain\Containers;

/**
 * Decide si un contenedor DEBERIA estar corriendo.
 *
 * En un servidor real la mayoria de los contenedores detenidos lo estan a
 * proposito: despliegues antiguos, tareas de una sola ejecucion, servicios
 * retirados. Marcarlos todos como caidos llena el informe de falsos positivos
 * y el cliente deja de confiar en el.
 *
 * El primer servidor real de prueba tenia 22 contenedores detenidos de 48, casi
 * todos intencionados. Ese dato motivo esta regla.
 */
class ContainerExpectation
{
    /**
     * Politicas de Docker que implican "este contenedor debe seguir vivo".
     *
     * @var array<int, string>
     */
    private const KEEP_RUNNING_POLICIES = ['always', 'unless-stopped', 'on-failure'];

    /**
     * @param  array<string, mixed>  $container
     * @param  array<int, string>  $explicitExpected  Nombres declarados en el check.
     */
    public static function isExpected(array $container, array $explicitExpected = []): bool
    {
        $name = (string) ($container['name'] ?? '');

        if ($name !== '' && in_array($name, $explicitExpected, true)) {
            return true;
        }

        $policy = mb_strtolower(trim((string) ($container['restart_policy'] ?? 'no')));

        return in_array($policy, self::KEEP_RUNNING_POLICIES, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $containers
     * @param  array<int, string>  $explicitExpected
     * @return array<int, array<string, mixed>>
     */
    public static function expected(array $containers, array $explicitExpected = []): array
    {
        return array_values(array_filter(
            $containers,
            static fn (array $container): bool => self::isExpected($container, $explicitExpected),
        ));
    }

    /**
     * @param  array<string, mixed>  $container
     */
    public static function isRunning(array $container): bool
    {
        return in_array(
            mb_strtolower((string) ($container['state'] ?? '')),
            ['running', 'restarting'],
            true,
        );
    }
}
