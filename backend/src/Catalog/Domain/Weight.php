<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Weight
{
    private const int DECIMALS = 3;

    private const int MAX_GRAMS = 2147483647;

    private function __construct(private int $grams) {}

    public static function fromDecimalString(string $value): self
    {
        return self::fromGrams(ScaledDecimal::parse('weight_kg', $value, self::DECIMALS));
    }

    public static function fromOptionalDecimalString(string $value): ?self
    {
        return trim($value) === '' ? null : self::fromDecimalString($value);
    }

    public static function fromGrams(int $grams): self
    {
        if ($grams < 0) {
            throw InvalidProductAttribute::because('weight_kg', 'must not be negative');
        }

        if ($grams > self::MAX_GRAMS) {
            throw InvalidProductAttribute::because('weight_kg', sprintf('must be at most %d grams', self::MAX_GRAMS));
        }

        return new self($grams);
    }

    public function grams(): int
    {
        return $this->grams;
    }

    public function toKilogramString(): string
    {
        return ScaledDecimal::format($this->grams, self::DECIMALS);
    }
}
