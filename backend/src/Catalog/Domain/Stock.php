<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Stock
{
    private function __construct(private int $quantity) {}

    public static function from(int|string $value): self
    {
        $trimmed = trim((string) $value);

        if (preg_match('/^\d+$/D', $trimmed) !== 1) {
            throw InvalidProductAttribute::because('stock', 'must be a non-negative integer');
        }

        $quantity = filter_var(ltrim($trimmed, '0') ?: '0', FILTER_VALIDATE_INT);

        if ($quantity === false) {
            throw InvalidProductAttribute::because('stock', 'is too large');
        }

        return new self($quantity);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }
}
