<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Lightweight API query helper to apply filtering, searching, sorting and pagination.
 *
 * Usage:
 *   $results = ApiQuery::apply($request, Model::query(), searchable: ['first_name','last_name'], allowedFilters: ['sex','company_id']);
 *   // $results is either a LengthAwarePaginator (when paginate=true) or a Collection
 */
class ApiQuery
{
    /**
    * Apply query parameters to a Builder.
     *
     * Recognized params:
     * - filter[field]=value  (exact match, can be array values)
     * - q=search-term        (search across searchable columns)
     * - sort=field,-other    (comma-separated, prefix - for DESC)
     * - per_page=N, page=N
     * - paginate=0|1 (default 1)
    * - limit=N (when paginate=0)
     * - all=1 (disable pagination/limit)
    * - with=relation1,relation2 (eager load relations)
    *
    * You can pass default options via the `$defaults` argument. Supported keys:
    * - `filter` => array of default filters
    * - `q` => default search string
    * - `sort` => default sort string
    * - `per_page` => default per_page when paginating
    * - `limit` => default limit when not paginating
    * - `paginate` => default paginate boolean
    * - `all` => default for returning all records
    * - `with` => default relations to eager load (comma-separated string)
     */
    public static function apply(Request $request, Builder|\Illuminate\Database\Query\Builder $query, array|string $searchable = [], array|string $allowedFilters = 'all', array $defaults = [])
    {

        // Validate inputs
        $request->validate([
            'filter' => 'sometimes|array',
            'q' => 'sometimes|string|max:255',
            'sort' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'all' => 'sometimes|boolean',
            'paginate' => 'sometimes|in:true,false,1,0',
            'with' => 'sometimes|string|max:255',
        ]);


        // Eager loading (use default if request missing)
        $with = $request->input('with', $defaults['with'] ?? null);
        if ($with) {
            $relations = array_map('trim', explode(',', $with));
            $query->with($relations);
        }

        // Filters (respect defaults when not provided in request)
        $filters = $request->input('filter', $defaults['filter'] ?? []);
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (is_array($allowedFilters) && !in_array($key, $allowedFilters, true)) {
                    continue;
                }

                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        // Search (default from $defaults if present)
        $q = trim((string) $request->input('q', $defaults['q'] ?? ''));
        if ($q !== '' && count($searchable) > 0) {

            // split query into tokens: "Adam   Kohane" -> ["Adam","Kohane"]
            $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

            // If somehow split fails, fallback to whole string
            if (!$tokens || count($tokens) === 0) {
                $tokens = [$q];
            }

            $query->where(function (Builder $outer) use ($searchable, $tokens) {

                // AND between tokens (must match all tokens)
                foreach ($tokens as $token) {
                    $like = "%{$token}%";

                    $outer->where(function (Builder $inner) use ($searchable, $like) {
                        // OR between columns (token can match any searchable column)
                        foreach ($searchable as $i => $col) {
                            $expr = "public.immutable_unaccent(lower(cast({$col} as text))) LIKE public.immutable_unaccent(lower(?))";

                            if ($i === 0) {
                                $inner->whereRaw($expr, [$like]);
                            } else {
                                $inner->orWhereRaw($expr, [$like]);
                            }
                        }
                    });
                }
            });
        }

        // Sorting (use default if request missing)
        $sort = $request->input('sort', $defaults['sort'] ?? null);
        if ($sort) {
            $parts = explode(',', $sort);
            foreach ($parts as $part) {
                $direction = 'asc';
                $field = $part;
                if (str_starts_with($part, '-')) {
                    $direction = 'desc';
                    $field = substr($part, 1);
                }
                $query->orderBy($field, $direction);
            }
        }

        // Pagination (defaults may override)
        $paginate = $request->boolean('paginate', $defaults['paginate'] ?? true);
        $perPage = (int) $request->input('per_page', $defaults['per_page'] ?? 15);
        $limit = (int) $request->input('limit', $defaults['limit'] ?? -1);
        if ($request->boolean('all', $defaults['all'] ?? false)) {
            $paginate = false;
            $limit = -1;
        }

        $sql = $query->toRawSql();


        if (!$paginate) {
            if ($limit > 0) {
                $query->limit($limit);
            }
            $result = $query->get();
        } else {
            $result = $query->paginate($perPage)->withQueryString();
        }

        $result->sql = $sql;
        return $result;
    }
}
