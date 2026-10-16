<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Sku
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $canonical = mb_strtoupper(RequiredText::from('sku', $value, self::MAX_LENGTH));

        if (mb_strlen($canonical) > self::MAX_LENGTH) {
            throw InvalidProductAttribute::because('sku', sprintf('must not exceed %d characters', self::MAX_LENGTH));
        }

        return new self($canonical);
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
