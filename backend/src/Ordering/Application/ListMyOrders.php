<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderRepository;

final readonly class ListMyOrders
{
    public function __construct(private OrderRepository $orders) {}

    /** @return array{orders: list<Order>, total: int} */
    public function handle(int $userId, int $page, int $perPage): array
    {
        return $this->orders->pageForUser($userId, $page, $perPage);
    }
}
