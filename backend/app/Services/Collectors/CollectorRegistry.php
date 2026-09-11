<?php

namespace App\Services\Collectors;

use App\Domain\Enums\CheckType;
use App\Models\Check;
use App\Services\Collectors\Contracts\Collector;
use App\Services\Collectors\Exceptions\CollectionFailed;
use Illuminate\Contracts\Container\Container;

/**
 * Resuelve que collector corresponde a cada tipo de check.
 *
 * Anadir un tipo recolectado por la plataforma es anadir una clase y
 * registrarla aqui.
 */
class CollectorRegistry
{
    /** @var array<int, string> */
    private const COLLECTORS = [
        HttpCheckCollector::class,
        SslCheckCollector::class,
        GithubWorkflowCollector::class,
    ];

    /** @var array<string, Collector>|null */
    private ?array $resolved = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function for(CheckType $type): ?Collector
    {
        foreach ($this->all() as $collector) {
            if (in_array($type, $collector->supports(), true)) {
                return $collector;
            }
        }

        return null;
    }

    public function supports(CheckType $type): bool
    {
        return $this->for($type) !== null;
    }

    /**
     * @return array<int, Collector>
     */
    public function all(): array
    {
        if ($this->resolved === null) {
            $this->resolved = [];

            foreach (self::COLLECTORS as $class) {
                /** @var Collector $instance */
                $instance = $this->container->make($class);
                $this->resolved[] = $instance;
            }
        }

        return $this->resolved;
    }

    public function resolveOrFail(Check $check): Collector
    {
        return $this->for($check->type)
            ?? throw new CollectionFailed("No hay collector registrado para el tipo {$check->type->value}.");
    }
}
