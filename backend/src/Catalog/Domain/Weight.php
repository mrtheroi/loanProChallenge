<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Weight
{
    private const int DECIMALS = 3;

    private function __construct(private int $grams) {}

    public static function fromDecimalString(string $value): self
    {
        return new self(ScaledDecimal::parse('weight_kg', $value, self::DECIMALS));
    }

    public static function fromOptionalDecimalString(string $value): ?self
    {
        return trim($value) === '' ? null : self::fromDecimalString($value);
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
