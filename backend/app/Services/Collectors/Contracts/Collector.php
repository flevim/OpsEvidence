<?php

namespace App\Services\Collectors\Contracts;

use App\Domain\Enums\CheckType;
use App\Models\Check;
use App\Services\Collectors\Exceptions\CollectionFailed;
use App\Services\Evidence\EvidencePayload;

interface Collector
{
    /**
     * Tipos de check que este collector sabe resolver.
     *
     * @return array<int, CheckType>
     */
    public function supports(): array;

    /**
     * Ejecuta la recoleccion y devuelve la evidencia normalizada.
     *
     * Un fallo de red que demuestra que el activo esta mal se devuelve como
     * evidencia CRITICAL. Un fallo que impide medir (handshake TLS roto,
     * API que responde 500) se devuelve como FAILED. Solo se lanza excepcion
     * ante configuracion invalida.
     *
     * @return array<int, EvidencePayload>
     *
     * @throws CollectionFailed
     */
    public function collect(Check $check): array;
}
