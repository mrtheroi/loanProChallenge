<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\ProductAlreadyExists;
use Ecommerce\Catalog\Domain\ProductNotFound;
use Ecommerce\Catalog\Domain\SkuIsReserved;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CatalogExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (InvalidProductAttribute $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalid($exception->field(), $exception->reason())
            : null);
        $exceptions->render(fn (ProductAlreadyExists $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalid('sku', 'already exists')
            : null);
        $exceptions->render(fn (SkuIsReserved $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalid('sku', 'belongs to a deleted product and cannot be reused')
            : null);
        $exceptions->render(fn (ProductNotFound $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? new JsonResponse(['message' => 'Product not found.'], 404)
            : null);
    }

    private static function invalid(string $field, string $reason): JsonResponse
    {
        return new JsonResponse(['message' => $reason, 'errors' => [$field => [$reason]]], 422);
    }
}
