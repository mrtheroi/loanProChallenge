<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

interface ProductRepository
{
    public function save(Product $product): void;

    public function findBySku(Sku $sku): ?Product;
}
