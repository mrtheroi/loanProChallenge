<?php

declare(strict_types=1);

use Ecommerce\Ordering\Application\PlaceOrderCommand;
use Ecommerce\Ordering\Domain\PaymentCard;

it('requires an expected price for every ordered sku', function (): void {
    expect(fn () => new PlaceOrderCommand(1, 'key', ['MUG-1' => 1, 'CUP-1' => 1], ['MUG-1' => 1699], PaymentCard::fromInput('4242424242424242', 12, 2030, '123'), new DateTimeImmutable('2026-10-16')))
        ->toThrow(InvalidArgumentException::class, 'Missing expected price for CUP-1.');
});
