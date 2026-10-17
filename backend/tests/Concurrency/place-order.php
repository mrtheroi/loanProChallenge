<?php

declare(strict_types=1);

use Ecommerce\Ordering\Application\PlaceOrder;
use Ecommerce\Ordering\Application\PlaceOrderCommand;
use Ecommerce\Ordering\Domain\InsufficientStock;
use Ecommerce\Ordering\Domain\PaymentCard;
use Ecommerce\Ordering\Domain\StockLedger;
use Illuminate\Contracts\Console\Kernel;

[, $userId, $key, $sku, $quantity, $expectedPriceCents, $holdMs] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$real = $app->make(StockLedger::class);
$app->instance(StockLedger::class, new readonly class($real, (int) $holdMs) implements StockLedger
{
    public function __construct(private StockLedger $real, private int $holdMs) {}

    public function lock(array $skus): array
    {
        $items = $this->real->lock($skus);
        usleep($this->holdMs * 1000);

        return $items;
    }

    public function decrement(array $quantitiesByProductId): void
    {
        $this->real->decrement($quantitiesByProductId);
    }
});

fwrite(STDERR, "ready\n");
fgets(STDIN);

try {
    $placed = $app->make(PlaceOrder::class)->handle(new PlaceOrderCommand(
        (int) $userId,
        $key,
        [$sku => (int) $quantity],
        [$sku => (int) $expectedPriceCents],
        PaymentCard::fromInput('4242424242424242', 12, 2030, '123'),
        new DateTimeImmutable('2026-10-16'),
    ));
    echo json_encode(['outcome' => $placed->replayed ? 'replayed' : 'created', 'order' => $placed->order->id]);
} catch (InsufficientStock) {
    echo json_encode(['outcome' => 'insufficient']);
} catch (Throwable $exception) {
    echo json_encode(['outcome' => 'error', 'error' => $exception::class.': '.$exception->getMessage()]);
}
