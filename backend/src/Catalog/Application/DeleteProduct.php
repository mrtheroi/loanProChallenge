<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\ProductRepository;

final readonly class DeleteProduct
{
    public function __construct(private ProductRepository $products) {}

    public function handle(string $sku): void
    {
        $this->products->delete(ExistingSku::parse($sku));
    }
}
