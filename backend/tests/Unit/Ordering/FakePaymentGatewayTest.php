<?php

declare(strict_types=1);

use Ecommerce\Ordering\Domain\PaymentCard;
use Ecommerce\Ordering\Domain\PaymentDeclined;
use Ecommerce\Ordering\Infrastructure\FakePaymentGateway;

function testCard(string $number): PaymentCard
{
    return PaymentCard::fromInput($number, 12, 2030, '123', new DateTimeImmutable('2026-10-16'));
}

it('approves the 4242 test card and returns a charge reference', function (): void {
    $charge = (new FakePaymentGateway)->charge(testCard('4242424242424242'), 1999);

    expect($charge->reference)->toStartWith('fake_ch_')
        ->and($charge->amountCents)->toBe(1999);
});

it('declines the 0002 test card and any other number', function (string $number, string $reason): void {
    expect(fn () => (new FakePaymentGateway)->charge(testCard($number), 1999))
        ->toThrow(PaymentDeclined::class, $reason);
})->with([
    'decline card' => ['4000000000000002', 'Your card was declined.'],
    'unknown card' => ['5555555555554444', 'This card is not supported in test mode; use 4242 4242 4242 4242.'],
]);
