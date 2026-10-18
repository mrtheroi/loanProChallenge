<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\Product;

final readonly class ProductPage
{
    /** @param list<Product> $products */
    public function __construct(
        public array $products,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
