<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DateTimeImmutable;
use Stringable;

final readonly class PaymentCard implements Stringable
{
    private function __construct(private string $number) {}

    public static function fromInput(string $number, int $expMonth, int $expYear, string $cvc, DateTimeImmutable $today): self
    {
        $digits = str_replace([' ', '-'], '', $number);

        if (preg_match('/^\d{13,19}$/D', $digits) !== 1) {
            throw InvalidOrderInput::because('card.number', 'must contain 13 to 19 digits');
        }

        if ($expMonth < 1 || $expMonth > 12) {
            throw InvalidOrderInput::because('card.exp_month', 'must be between 1 and 12');
        }

        if (sprintf('%04d%02d', $expYear, $expMonth) < $today->format('Ym')) {
            throw InvalidOrderInput::because('card.exp_year', 'the card has expired');
        }

        if (preg_match('/^\d{3,4}$/D', $cvc) !== 1) {
            throw InvalidOrderInput::because('card.cvc', 'must contain 3 or 4 digits');
        }

        return new self($digits);
    }

    public function number(): string
    {
        return $this->number;
    }

    public function last4(): string
    {
        return substr($this->number, -4);
    }

    public function brand(): string
    {
        return match (true) {
            str_starts_with($this->number, '4') => 'visa',
            preg_match('/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $this->number) === 1 => 'mastercard',
            preg_match('/^3[47]/', $this->number) === 1 => 'amex',
            default => 'unknown',
        };
    }

    public function __toString(): string
    {
        return "{$this->brand()} ending in {$this->last4()}";
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['card' => (string) $this];
    }
}
