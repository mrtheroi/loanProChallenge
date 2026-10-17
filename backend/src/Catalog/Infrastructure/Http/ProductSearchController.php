<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Application\ProductSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProductSearchController
{
    public function __invoke(ProductSearchRequest $request, ProductSearch $search): AnonymousResourceCollection
    {
        $page = $search->search($request->toCriteria());

        return ProductResource::collection($page->products)->additional(['meta' => [
            'current_page' => $page->page,
            'per_page' => $page->perPage,
            'total' => $page->total,
            'last_page' => $page->lastPage(),
        ]]);
    }
}
