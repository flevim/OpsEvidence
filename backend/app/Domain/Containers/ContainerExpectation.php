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
     * Política de Docker que implica de verdad "este contenedor debe seguir vivo".
     *
     * Solo `always`. La semántica de las demás es la contraria de lo que parece:
     *
     *  - `unless-stopped`: reinicia *salvo que lo hayan detenido a propósito*.
     *    Si está detenido, alguien lo paró: no es una incidencia.
     *  - `on-failure`: solo reinicia si falló. Estar detenido puede significar
     *    que terminó bien.
     *
     * En el servidor real de prueba, 45 de 48 contenedores tenían política
     * (Compose pone `unless-stopped` por defecto), así que contar cualquiera de
     * ellas como "debe estar corriendo" no filtraba nada: 19 falsos positivos.
     *
     * @var array<int, string>
     */
    private const KEEP_RUNNING_POLICIES = ['always'];

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
