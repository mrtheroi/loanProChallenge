<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Application\GetProduct;
use Ecommerce\Catalog\Application\ListCategories;
use Illuminate\Http\JsonResponse;

final class PublicCatalogController
{
    public function show(string $sku, GetProduct $getProduct): ProductResource
    {
        return new ProductResource($getProduct->handle($sku));
    }

    public function categories(ListCategories $listCategories): JsonResponse
    {
        return new JsonResponse(['data' => $listCategories->handle()]);
    }
}
