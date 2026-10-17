<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use Ecommerce\Ordering\Domain\Order;

final readonly class PlacedOrder
{
    public function __construct(
        public Order $order,
        public bool $replayed,
    ) {}
}
