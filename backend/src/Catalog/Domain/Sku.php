<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Sku
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        return new self(RequiredText::from('sku', $value, self::MAX_LENGTH));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
