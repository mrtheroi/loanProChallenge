<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use Ecommerce\Ordering\Domain\PaymentCard;

final readonly class PlaceOrderCommand
{
    /** @param  array<string, int>  $quantitiesBySku  canonical SKU => quantity */
    public function __construct(
        public int $userId,
        public string $idempotencyKey,
        public array $quantitiesBySku,
        public PaymentCard $card,
        public int $cardExpMonth,
        public int $cardExpYear,
        public string $paymentFingerprint,
    ) {}

    public function requestHash(): string
    {
        $lines = [];
        foreach ($this->quantitiesBySku as $sku => $quantity) {
            $lines[] = [(string) $sku, $quantity];
        }
        usort($lines, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return hash('sha256', (string) json_encode([
            'items' => $lines,
            'card' => [$this->paymentFingerprint, $this->cardExpMonth, $this->cardExpYear],
        ]));
    }
}
