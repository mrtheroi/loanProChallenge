<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Product
{
    private function __construct(
        private Sku $sku,
        private ProductName $name,
        private ?string $description,
        private CategoryName $category,
        private Money $price,
        private Stock $stock,
        private ?Weight $weight,
    ) {}

    public static function create(
        Sku $sku,
        ProductName $name,
        ?string $description,
        CategoryName $category,
        Money $price,
        Stock $stock,
        ?Weight $weight,
    ): self {
        $description = trim($description ?? '');

        if (! mb_check_encoding($description, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $description) === 1) {
            throw InvalidProductAttribute::because('description', 'must be valid UTF-8 text without control characters');
        }

        return new self($sku, $name, $description === '' ? null : $description, $category, $price, $stock, $weight);
    }

    public function sku(): Sku
    {
        return $this->sku;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): CategoryName
    {
        return $this->category;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function stock(): Stock
    {
        return $this->stock;
    }

    public function weight(): ?Weight
    {
        return $this->weight;
    }
}
