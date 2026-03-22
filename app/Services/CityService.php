<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Database\Eloquent\Collection;

/**
 * Business logic for city suggestions and ZIP lookups.
 */
class CityService
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 25;

    /**
     * Suggest cities by name prefix or ZIP prefix.
     */
    public function suggest(string $query, ?int $limit = null): Collection
    {
        $normalizedQuery = trim($query);
        $normalizedQueryNoSpaces = $this->removeSpaces($normalizedQuery);
        $resolvedLimit = $this->resolveLimit($limit);

        $builder = City::query();

        if ($this->isZipLikeQuery($normalizedQueryNoSpaces)) {
            $builder->whereRaw(
                "regexp_replace(zip, '\\s+', '', 'g') LIKE ?",
                [$normalizedQueryNoSpaces . '%']
            );
        } else {
            $builder->whereRaw(
                "unaccent(lower(name)) LIKE unaccent(lower(?))",
                [$normalizedQuery . '%']
            );
        }

        return $builder
            ->orderBy('name')
            ->limit($resolvedLimit)
            ->get(['id', 'name', 'zip']);
    }

    /**
     * Find the first city with an exact ZIP match (spaces ignored).
     */
    public function findByZip(string $zip): ?City
    {
        $normalizedZip = $this->removeSpaces(trim($zip));

        return City::query()
            ->whereRaw(
                "regexp_replace(zip, '\\s+', '', 'g') = ?",
                [$normalizedZip]
            )
            ->orderBy('name')
            ->first(['id', 'name', 'zip']);
    }

    /**
     * Resolve limit while keeping it in a safe boundary.
     */
    private function resolveLimit(?int $limit): int
    {
        if ($limit === null) {
            return self::DEFAULT_LIMIT;
        }

        return min(max($limit, 1), self::MAX_LIMIT);
    }

    /**
     * Remove all spaces from a value.
     */
    private function removeSpaces(string $value): string
    {
        return preg_replace('/\s+/', '', $value) ?? '';
    }

    /**
     * Detect whether the user query looks like a ZIP prefix.
     */
    private function isZipLikeQuery(string $query): bool
    {
        return preg_match('/^\d+$/', $query) === 1;
    }
}
