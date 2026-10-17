<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

final readonly class OrderLine
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $unitPriceCents,
        public int $quantity,
    ) {}

    public function totalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }
}
