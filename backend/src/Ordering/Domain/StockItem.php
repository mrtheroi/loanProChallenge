<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

final readonly class StockItem
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $priceCents,
        public int $stock,
    ) {}
}
