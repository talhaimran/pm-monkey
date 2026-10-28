<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Mcp\Response;

trait RespondsWithJson
{
    /** One text block: a short human summary, then compact JSON. */
    protected function result(string $summary, mixed $data): Response
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        }

        return Response::text($summary."\n\n".json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Paginated list with a summary that tells the agent how to fetch more. */
    protected function paginated(string $noun, LengthAwarePaginator $page, string $resource): Response
    {
        $more = $page->hasMorePages() ? ' Pass page='.($page->currentPage() + 1).' for more.' : '';

        return $this->result(
            "Showing {$page->count()} of {$page->total()} {$noun} (page {$page->currentPage()}).{$more}",
            [
                'data' => $resource::collection($page->getCollection())->resolve(),
                'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'has_more' => $page->hasMorePages()],
            ],
        );
    }
}
