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

    public function __construct($resource, ?string $resourceClass = null)
    {
        parent::__construct($resource);
        if ($resourceClass !== null) {
            $this->resourceClass = $resourceClass;
        }
    }

    public function toArray($request): array
    {
        $items = ($this->resourceClass) ?
            $this->collection->map(fn($item) => (new $this->resourceClass($item))->toArray($request))
            :
            $items = $this->collection->toArray();

        return [
            'items' => $items,
            'count' => $this->collection->count(),
        ];
    }
}
