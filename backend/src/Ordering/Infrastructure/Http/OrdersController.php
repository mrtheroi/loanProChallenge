<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use Ecommerce\Ordering\Application\GetMyOrder;
use Ecommerce\Ordering\Application\ListMyOrders;
use Ecommerce\Ordering\Application\PlaceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrdersController
{
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $placed = $placeOrder->handle($request->toCommand());

        return (new OrderResource($placed->order))->response()->setStatusCode($placed->replayed ? 200 : 201);
    }

    public function index(ListOrdersRequest $request, ListMyOrders $listMyOrders): AnonymousResourceCollection
    {
        $page = $request->filled('page') ? $request->integer('page') : 1;
        $perPage = $request->filled('per_page') ? $request->integer('per_page') : ListOrdersRequest::DEFAULT_PER_PAGE;
        $result = $listMyOrders->handle(self::userId($request), $page, $perPage);

        return OrderResource::collection($result['orders'])->additional(['meta' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
        ]]);
    }

    public function show(Request $request, int $order, GetMyOrder $getMyOrder): OrderResource
    {
        return new OrderResource($getMyOrder->handle(self::userId($request), $order));
    }

    private static function userId(Request $request): int
    {
        return AuthenticatedUser::id($request);
    }
}
