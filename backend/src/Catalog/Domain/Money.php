<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final readonly class Money
{
    private const int DECIMALS = 2;

    private function __construct(private int $cents) {}

    public static function fromDecimalString(string $value): self
    {
        return new self(ScaledDecimal::parse('price', $value, self::DECIMALS));
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function toDecimalString(): string
    {
        return ScaledDecimal::format($this->cents, self::DECIMALS);
    }
}
