<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductRepository;

final readonly class UpdateProduct
{
    public function __construct(private ProductRepository $products) {}

    public function handle(string $sku, ProductData $data): Product
    {
        $pathSku = ExistingSku::parse($sku);
        $product = $data->toProduct();

        if (! $product->sku()->equals($pathSku)) {
            throw InvalidProductAttribute::because('sku', 'must match the sku of the product being updated');
        }

        $this->products->update($product);

        return $product;
    }
}
