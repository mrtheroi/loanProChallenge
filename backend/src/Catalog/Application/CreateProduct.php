<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductRepository;

final readonly class CreateProduct
{
    public function __construct(private ProductRepository $products) {}

    public function handle(ProductData $data): Product
    {
        $product = $data->toProduct();
        $this->products->add($product);

        return $product;
    }
}
