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
    ) {}

    public function requestHash(): string
    {
        $quantities = $this->quantitiesBySku;
        ksort($quantities);

        return hash('sha256', (string) json_encode([
            'items' => $quantities,
            'card' => [$this->card->brand(), $this->card->last4(), $this->cardExpMonth, $this->cardExpYear],
        ]));
    }
}
