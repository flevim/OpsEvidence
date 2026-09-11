<?php

namespace App\Services\Collectors\Exceptions;

use RuntimeException;

/**
 * Error de configuracion o de entorno que impide recolectar.
 * No implica que el activo este mal: se registra como fallo del check.
 */
class CollectionFailed extends RuntimeException
{
}
