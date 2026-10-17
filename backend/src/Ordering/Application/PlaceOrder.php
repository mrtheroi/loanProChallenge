<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Application;

use Ecommerce\Ordering\Domain\DuplicateIdempotencyKey;
use Ecommerce\Ordering\Domain\IdempotencyKeyReused;
use Ecommerce\Ordering\Domain\InsufficientStock;
use Ecommerce\Ordering\Domain\InvalidOrderInput;
use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderLine;
use Ecommerce\Ordering\Domain\OrderRepository;
use Ecommerce\Ordering\Domain\PaymentGateway;
use Ecommerce\Ordering\Domain\StockLedger;
use Ecommerce\Ordering\Domain\TransactionRunner;
use Ecommerce\Ordering\Domain\Unavailable;

final readonly class PlaceOrder
{
    public function __construct(
        private OrderRepository $orders,
        private StockLedger $stock,
        private PaymentGateway $payments,
        private TransactionRunner $transactions,
    ) {}

    public function handle(PlaceOrderCommand $command): PlacedOrder
    {
        $requestHash = $command->requestHash();
        $existing = $this->orders->findByIdempotencyKey($command->userId, $command->idempotencyKey);

        if ($existing !== null) {
            return $this->replay($existing, $requestHash);
        }

        try {
            return new PlacedOrder($this->transactions->run(fn (): Order => $this->place($command, $requestHash)), false);
        } catch (DuplicateIdempotencyKey) {
            $winner = $this->orders->findByIdempotencyKey($command->userId, $command->idempotencyKey)
                ?? throw IdempotencyKeyReused::withDifferentRequest();

            return $this->replay($winner, $requestHash);
        }
    }

    private function place(PlaceOrderCommand $command, string $requestHash): Order
    {
        if ($command->card->isExpiredOn($command->today)) {
            throw InvalidOrderInput::because('card.exp_year', 'the card has expired');
        }

        $orderId = $this->orders->claim($command->userId, $command->idempotencyKey, $requestHash, $command->card);

        $skus = array_map(strval(...), array_keys($command->quantitiesBySku));
        sort($skus, SORT_STRING);
        $items = $this->stock->lock($skus);

        $lines = [];
        $unavailable = [];
        foreach ($skus as $sku) {
            $quantity = $command->quantitiesBySku[$sku];
            $item = $items[$sku] ?? null;

            if ($item === null || $item->stock < $quantity) {
                $unavailable[] = new Unavailable($sku, $quantity, $item === null ? 0 : $item->stock);

                continue;
            }

            $lines[] = new OrderLine($item->productId, $item->sku, $item->name, $item->priceCents, $quantity);
        }

        if ($unavailable !== []) {
            throw InsufficientStock::for($unavailable);
        }

        $total = self::total($lines);
        $charge = $this->payments->charge($command->card, $total);

        $this->stock->decrement(array_combine(
            array_map(fn (OrderLine $line): int => $line->productId, $lines),
            array_map(fn (OrderLine $line): int => $line->quantity, $lines),
        ));

        return $this->orders->complete($orderId, $lines, $charge);
    }

    private function replay(Order $order, string $requestHash): PlacedOrder
    {
        if (! hash_equals($order->requestHash, $requestHash)) {
            throw IdempotencyKeyReused::withDifferentRequest();
        }

        return new PlacedOrder($order, true);
    }

    /** @param  list<OrderLine>  $lines */
    private static function total(array $lines): int
    {
        $total = 0;

        foreach ($lines as $line) {
            if ($line->unitPriceCents > intdiv(PHP_INT_MAX - $total, $line->quantity)) {
                throw InvalidOrderInput::because('items', 'the order total is too large');
            }

            $total += $line->totalCents();
        }

        return $total;
    }
}
