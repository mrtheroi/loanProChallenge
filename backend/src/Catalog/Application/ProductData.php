<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\Stock;
use Ecommerce\Catalog\Domain\Weight;

final readonly class ProductData
{
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $description,
        public string $category,
        public string $price,
        public int|string $stock,
        public ?string $weightKg,
    ) {}

    public function toProduct(): Product
    {
        return Product::create(
            Sku::fromString($this->sku),
            ProductName::fromString($this->name),
            $this->description,
            CategoryName::fromString($this->category),
            Money::fromDecimalString($this->price),
            Stock::from($this->stock),
            Weight::fromOptionalDecimalString($this->weightKg ?? ''),
        );
    }
}
