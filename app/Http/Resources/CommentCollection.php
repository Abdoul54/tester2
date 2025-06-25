<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CommentCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = CommentResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPaginated = $this->resource instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

        return [
            'data' => $this->collection,
            'pagination' => $this->when($isPaginated, function () {
                return [
                    'current_page' => $this->currentPage(),
                    'first_page_url' => $this->url(1),
                    'from' => $this->firstItem(),
                    'last_page' => $this->lastPage(),
                    'last_page_url' => $this->url($this->lastPage()),
                    'next_page_url' => $this->nextPageUrl(),
                    'path' => $this->path(),
                    'per_page' => $this->perPage(),
                    'prev_page_url' => $this->previousPageUrl(),
                    'to' => $this->lastItem(),
                    'total' => $this->total(),
                ];
            }),
            'meta' => [
                'count' => $this->collection->count(),
                'total' => $this->when($isPaginated, $this->total()),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'status' => 'success',
            'message' => 'Comments retrieved successfully',
            'timestamp' => now()->toISOString(),
        ];
    }
}
