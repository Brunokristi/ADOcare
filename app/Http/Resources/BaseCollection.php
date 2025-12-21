<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Generic collection wrapper that can optionally wrap items with a resource class.
 * Usage: new BaseCollection($items, CarResource::class) or extend and set $resourceClass.
 */
class BaseCollection extends ResourceCollection
{
    /**
     * Optional resource class to wrap each item with.
     */
    protected ?string $resourceClass = null;
    private ?string $sql = null;

    public function __construct($resource, ?string $resourceClass = null)
    {
        parent::__construct($resource);
        $this->sql = isset($resource->sql) ? $resource->sql : null;
        if ($resourceClass !== null) {
            $this->resourceClass = $resourceClass;
        }
    }

    public function toArray($request): array
    {
        if ($this->resourceClass) {
            $items = $this->collection->map(fn($item) => (new $this->resourceClass($item))->toArray($request));
        } else {
            $items = $this->collection->toArray();
        }

        $result = [
            'items' => $items,
            'count' => $this->collection->count(),
            'sql' => $this->sql ?? null,
        ];

        // If the underlying resource is a paginator, include pagination metadata
        if (isset($this->resource) && $this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            $p = $this->resource;
            $result['meta'] = [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
                'next_page_url' => $p->nextPageUrl(),
                'prev_page_url' => $p->previousPageUrl(),
            ];
        }

        return $result;
    }
}
