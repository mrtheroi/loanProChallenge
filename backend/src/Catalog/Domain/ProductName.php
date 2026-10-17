<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class ProductName
{
    private const int MAX_LENGTH = 255;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        return new self(RequiredText::from('name', $value, self::MAX_LENGTH));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function normalized(): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $this->value));
    }
}
