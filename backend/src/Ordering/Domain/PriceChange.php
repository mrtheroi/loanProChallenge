<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

final readonly class PriceChange
{
    public function __construct(
        public string $sku,
        public int $expectedCents,
        public int $currentCents,
    ) {}
}
