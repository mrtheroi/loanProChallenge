<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use DateTimeImmutable;
use Ecommerce\Ordering\Domain\PaymentCard;
use InvalidArgumentException;

final readonly class PlaceOrderCommand
{
    /**
     * @param  array<string, int>  $quantitiesBySku  canonical SKU => quantity
     * @param  array<string, int>  $expectedPriceCentsBySku  canonical SKU => unit price the buyer saw
     */
    public function __construct(
        public int $userId,
        public string $idempotencyKey,
        public array $quantitiesBySku,
        public array $expectedPriceCentsBySku,
        public PaymentCard $card,
        public DateTimeImmutable $today,
    ) {
        foreach (array_keys($quantitiesBySku) as $sku) {
            if (! array_key_exists($sku, $expectedPriceCentsBySku)) {
                throw new InvalidArgumentException("Missing expected price for {$sku}.");
            }
        }
    }

    /**
     * Identifies the request without any card secret: only brand, last four
     * and expiry are compared, so nothing derived from the number or CVC is stored.
     */
    public function requestHash(): string
    {
        $lines = [];
        foreach ($this->quantitiesBySku as $sku => $quantity) {
            $lines[] = [(string) $sku, $quantity, $this->expectedPriceCentsBySku[$sku]];
        }
        usort($lines, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return hash('sha256', (string) json_encode([
            'items' => $lines,
            'card' => [$this->card->brand(), $this->card->last4(), $this->card->expMonth(), $this->card->expYear()],
        ]));
    }
}
