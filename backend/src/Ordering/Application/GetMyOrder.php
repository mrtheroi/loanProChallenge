<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderNotFound;
use Ecommerce\Ordering\Domain\OrderRepository;

final readonly class GetMyOrder
{
    public function __construct(private OrderRepository $orders) {}

    public function handle(int $userId, int $orderId): Order
    {
        return $this->orders->findForUser($userId, $orderId) ?? throw new OrderNotFound;
    }
}
