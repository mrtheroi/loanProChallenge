<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

interface ProductRepository
{
    public function add(Product $product): Product;

    public function save(Product $product): void;

    public function update(Product $product): Product;

    public function findBySku(Sku $sku): ?Product;

    public function delete(Sku $sku): void;
}
