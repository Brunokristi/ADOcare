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
     * - filter[field]=value or filter[field][op]=value    (simple filters with optional operators) (operators: gt, gte, lt, lte, ne, neq, like, in)
     * - q=search-term          (search across searchable columns)
     * - sort=field,-other      (comma-separated, prefix - for DESC)
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


        // Apply modular parts
        static::applyWith($request, $query, $defaults);
        static::applyFilters($request, $query, $allowedFilters, $defaults);
        static::applySearch($request, $query, $searchable, $defaults);
        static::applySort($request, $query, $defaults);

        // Pagination (defaults may override)
        $result = static::applyPagination($request, $query, $defaults);

        // store raw SQL for debugging
        $result->sql = $query->toRawSql();
        return $result;
    }


    /**
     * Apply eager loading (with)
     */
    protected static function applyWith(Request $request, Builder $query, array $defaults = []): void
    {
        $with = $request->input('with', $defaults['with'] ?? null);
        if ($with) {
            $relations = array_map('trim', explode(',', $with));
            $query->with($relations);
        }
    }

    /**
     * Apply simple filters
     */
    protected static function applyFilters(Request $request, Builder $query, array|string $allowedFilters = 'all', array $defaults = []): void
    {
        $filters = $request->input('filter', $defaults['filter'] ?? []);
        if (!is_array($filters)) {
            return;
        }

        foreach ($filters as $key => $value) {
            if (is_array($allowedFilters) && !in_array($key, $allowedFilters, true)) {
                continue;
            }

            // If value is an array, decide if it's an indexed array (IN) or
            // an associative array of operators (e.g. ['gt' => 5, 'lte' => 10]).
            if (is_array($value)) {
                foreach ($value as $op => $operand) {
                    $op = strtolower(trim((string) $op));
                    switch ($op) {
                        case 'gt':
                            $query->where($key, '>', $operand);
                            break;
                        case 'gte':
                            $query->where($key, '>=', $operand);
                            break;
                        case 'lt':
                            $query->where($key, '<', $operand);
                            break;
                        case 'lte':
                            $query->where($key, '<=', $operand);
                            break;
                        case 'ne':
                        case 'neq':
                            $query->where($key, '!=', $operand);
                            break;
                        case 'like':
                            $query->where($key, 'LIKE', $operand);
                            break;
                        case 'in':
                            if (is_array($operand)) {
                                $query->whereIn($key, $operand);
                            }
                            break;
                        default:
                            // unknown operator — fallback to equality
                            $query->where($key, $operand);
                    }
                }
                continue;
            }

            // Scalar values: allow operator prefix like ">=10" or "< 5"
            if (is_string($value)) {
                if (preg_match('/^(>=|<=|!=|<>|>|<)\s*(.+)$/', $value, $m)) {
                    $op = $m[1];
                    $val = $m[2];
                    $query->where($key, $op, $val);
                    continue;
                }
            }

            // Fallback: exact match
            $query->where($key, $value);
        }
    }

    /**
     * Apply search across searchable columns.
     * Keeps original LIKE/unaccent behavior.
     */
    protected static function applySearch(Request $request, Builder $query, array|string $searchable = [], array $defaults = []): void
    {
        $q = trim((string) $request->input('q', $defaults['q'] ?? ''));
        if ($q === '' || count((array) $searchable) === 0) {
            return;
        }

        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens || count($tokens) === 0) {
            $tokens = [$q];
        }

        $query->where(function (Builder $outer) use ($searchable, $tokens) {
            foreach ($tokens as $token) {
                $like = "%{$token}%";

                $outer->where(function (Builder $inner) use ($searchable, $like) {
                    foreach ((array) $searchable as $i => $col) {
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

    /**
     * Apply sorting
     */
    protected static function applySort(Request $request, Builder $query, array $defaults = []): void
    {
        $sort = $request->input('sort', $defaults['sort'] ?? null);
        if (!$sort) {
            return;
        }

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

    /**
     * Apply pagination / limit and return results
     */
    protected static function applyPagination(Request $request, Builder $query, array $defaults = [])
    {
        $paginate = $request->boolean('paginate', $defaults['paginate'] ?? true);
        $perPage = (int) $request->input('per_page', $defaults['per_page'] ?? 15);
        $limit = (int) $request->input('limit', $defaults['limit'] ?? -1);
        if ($request->boolean('all', $defaults['all'] ?? false)) {
            $paginate = false;
            $limit = -1;
        }

        if (!$paginate) {
            if ($limit > 0) {
                $query->limit($limit);
            }
            return $query->get();
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
