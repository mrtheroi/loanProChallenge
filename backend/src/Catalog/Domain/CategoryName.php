<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class CategoryName
{
    private const int MAX_LENGTH = 100;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $name = RequiredText::from('category', $value, self::MAX_LENGTH);

        if (mb_strlen(self::normalize($name)) > self::MAX_LENGTH) {
            throw InvalidProductAttribute::because(
                'category',
                sprintf('must not exceed %d characters when lowercased', self::MAX_LENGTH),
            );
        }

        return new self($name);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function normalized(): string
    {
        return self::normalize($this->value);
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $name));
    }
}
