<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenamiento y paginacion seguros.
 *
 * El parametro `sort` NUNCA se interpola: debe pertenecer a una lista blanca
 * (ver docs/security.md, amenaza T4).
 */
trait HandlesListQuery
{
    /**
     * @param  array<int, string>  $allowed
     */
    protected function applySorting(Builder $query, Request $request, array $allowed, string $default): Builder
    {
        $sort = (string) $request->query('sort', $default);

        if (! in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        $order = mb_strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $order);
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);

        return max(1, min($perPage, 100));
    }
}
