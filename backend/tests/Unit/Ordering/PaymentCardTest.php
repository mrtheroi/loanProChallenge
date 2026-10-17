<?php

declare(strict_types=1);

use Ecommerce\Ordering\Domain\InvalidOrderInput;
use Ecommerce\Ordering\Domain\PaymentCard;

function card(string $number = '4242 4242 4242 4242', int $month = 12, int $year = 2030, string $cvc = '123'): PaymentCard
{
    return PaymentCard::fromInput($number, $month, $year, $cvc, new DateTimeImmutable('2026-10-16'));
}

it('keeps only the digits of the number and exposes brand and last four', function (string $number, string $brand): void {
    $card = card($number);

    expect($card->number())->toBe(preg_replace('/\D/', '', $number))
        ->and($card->brand())->toBe($brand)
        ->and($card->last4())->toBe(substr((string) preg_replace('/\D/', '', $number), -4));
})->with([
    'visa' => ['4242 4242 4242 4242', 'visa'],
    'mastercard' => ['5555555555554444', 'mastercard'],
    'amex' => ['378282246310005', 'amex'],
    'other' => ['6011111111111117', 'unknown'],
]);

it('rejects an invalid card', function (string $number, int $month, int $year, string $cvc, string $field): void {
    expect(fn () => card($number, $month, $year, $cvc))->toThrow(function (InvalidOrderInput $e) use ($field): void {
        expect($e->field())->toBe("card.{$field}");
    });
})->with([
    'letters' => ['4242abcd42424242', 12, 2030, '123', 'number'],
    'too short' => ['424242424242', 12, 2030, '123', 'number'],
    'too long' => ['42424242424242424242', 12, 2030, '123', 'number'],
    'month zero' => ['4242424242424242', 0, 2030, '123', 'exp_month'],
    'month thirteen' => ['4242424242424242', 13, 2030, '123', 'exp_month'],
    'expired last month' => ['4242424242424242', 9, 2026, '123', 'exp_year'],
    'expired last year' => ['4242424242424242', 12, 2025, '123', 'exp_year'],
    'cvc too short' => ['4242424242424242', 12, 2030, '12', 'cvc'],
    'cvc letters' => ['4242424242424242', 12, 2030, '12a', 'cvc'],
]);

it('accepts a card that expires in the current month', function (): void {
    expect(card('4242424242424242', 10, 2026)->last4())->toBe('4242');
});

it('never exposes the cvc or the full number when cast to string or debugged', function (): void {
    $card = card();

    expect((string) $card)->toBe('visa ending in 4242')
        ->and(print_r($card, true))->not->toContain('4242424242424242')
        ->and(print_r($card, true))->not->toContain('123');
});
