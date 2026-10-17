<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Application\CreateProduct;
use Ecommerce\Catalog\Application\DeleteProduct;
use Ecommerce\Catalog\Application\GetProduct;
use Ecommerce\Catalog\Application\UpdateProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AdminProductController
{
    public function store(ProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        return (new ProductResource($createProduct->handle($request->toProductData())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $sku, GetProduct $getProduct): ProductResource
    {
        return new ProductResource($getProduct->handle($sku));
    }

    public function update(string $sku, ProductRequest $request, UpdateProduct $updateProduct): ProductResource
    {
        return new ProductResource($updateProduct->handle($sku, $request->toProductData($sku)));
    }

    public function destroy(string $sku, DeleteProduct $deleteProduct): Response
    {
        $deleteProduct->handle($sku);

        return response()->noContent();
    }
}
