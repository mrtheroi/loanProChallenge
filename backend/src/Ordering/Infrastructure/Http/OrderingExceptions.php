<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use Ecommerce\Ordering\Domain\IdempotencyKeyReused;
use Ecommerce\Ordering\Domain\InsufficientStock;
use Ecommerce\Ordering\Domain\InvalidOrderInput;
use Ecommerce\Ordering\Domain\OrderNotFound;
use Ecommerce\Ordering\Domain\PaymentDeclined;
use Ecommerce\Ordering\Domain\Unavailable;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderingExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (InvalidOrderInput $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalid($exception->field(), $exception->reason())
            : null);
        $exceptions->render(fn (IdempotencyKeyReused $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalid('idempotency_key', $exception->getMessage())
            : null);
        $exceptions->render(fn (InsufficientStock $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? new JsonResponse([
                'message' => $exception->getMessage(),
                'unavailable' => array_map(fn (Unavailable $item): array => [
                    'sku' => $item->sku,
                    'requested' => $item->requested,
                    'available' => $item->available,
                ], $exception->items),
            ], 409)
            : null);
        $exceptions->render(fn (OrderNotFound $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? new JsonResponse(['message' => 'Order not found.'], 404)
            : null);
        $exceptions->render(fn (PaymentDeclined $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? new JsonResponse(['message' => $exception->getMessage()], 402)
            : null);
    }

    private static function invalid(string $field, string $reason): JsonResponse
    {
        return new JsonResponse(['message' => $reason, 'errors' => [$field => [$reason]]], 422);
    }
}
