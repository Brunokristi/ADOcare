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
     */
    public static function apply(Request $request, Builder $query, array $searchable = [], array $allowedFilters = [])
    {

        // Validate inputs
        $request->validate([
            'filter' => 'sometimes|array',
            'q' => 'sometimes|string|max:255',
            'sort' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'all' => 'sometimes|boolean',
            'paginate' => 'sometimes|boolean',
            'with' => 'sometimes|string|max:255',
        ]);

        // Filters
        $filters = $request->input('filter', []);
        if (is_array($filters)) {
            foreach ($filters as $key => $value) {
                if (!empty($allowedFilters) && !in_array($key, $allowedFilters, true)) {
                    continue;
                }

                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        // Search
        $q = $request->input('q');
        if ($q && count($searchable) > 0) {
            $query->where(function (Builder $b) use ($searchable, $q) {
                foreach ($searchable as $i => $col) {
                    if ($i === 0) {
                        $b->where($col, 'ILIKE', "%{$q}%");
                    } else {
                        $b->orWhere($col, 'ILIKE', "%{$q}%");
                    }
                }
            });
        }

        // Sorting
        $sort = $request->input('sort');
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

        // Pagination
        $paginate = $request->boolean('paginate', true);
        $perPage = (int) $request->input('per_page', 15);
        $limit = (int) $request->input('limit', 100);
        if ($request->boolean('all')) {
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
