<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Sku
{
    private const int MAX_LENGTH = 64;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $text = RequiredText::from('sku', $value, self::MAX_LENGTH);

        if (preg_match('/^[A-Za-z0-9._-]+$/D', $text) !== 1) {
            throw InvalidProductAttribute::because('sku', 'must contain only letters A-Z, digits, dashes, underscores and dots');
        }

        return new self(strtoupper($text));
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
