<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

trait MobileApiHelpers
{
    protected function paginated(LengthAwarePaginator $paginator, string $key = 'items'): JsonResponse
    {
        return response()->json([
            $key => $paginator->getCollection()->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    protected function perPage($request, int $default = 15, int $max = 100): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    protected function scopeUnitQuery(Builder $query, $user, string $column = 'organization_unit_id'): Builder
    {
        if ($user->canViewGlobalScope()) {
            return $query;
        }

        $unitId = $user->currentUnitId();

        if (! $unitId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $unitId);
    }

    protected function ok(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok'] + $payload, $status);
    }
}
