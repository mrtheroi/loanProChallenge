<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductNotFound;
use Ecommerce\Catalog\Domain\ProductRepository;

final readonly class GetProduct
{
    public function __construct(private ProductRepository $products) {}

    public function handle(string $sku): Product
    {
        return $this->products->findBySku(ExistingSku::parse($sku)) ?? throw ProductNotFound::bySku($sku);
    }
}
