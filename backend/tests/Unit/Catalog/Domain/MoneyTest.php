<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Money;

it('stores a decimal string as integer cents', function (string $input, int $cents): void {
    expect(Money::fromDecimalString($input)->cents())->toBe($cents);
})->with([
    'zero' => ['0', 0],
    'zero with decimals' => ['0.00', 0],
    'whole number' => ['22', 2200],
    'one decimal' => ['45.5', 4550],
    'two decimals' => ['45.50', 4550],
    'typical price' => ['89.99', 8999],
    'surrounding whitespace' => [' 12.30 ', 1230],
]);

it('rejects anything that is not a non-negative decimal with at most 2 decimals', function (string $input): void {
    expect(fn () => Money::fromDecimalString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('price')
            ->and($e->reason())->not->toBeEmpty();
    });
})->with([
    'currency symbol' => '$29.99',
    'word' => 'free',
    'negative' => '-1',
    'more than 2 decimals' => '1.234',
    'empty' => '',
    'whitespace only' => '   ',
    'thousands separator' => '1,000.00',
    'decimal comma' => '29,99',
    'trailing dot' => '29.',
    'leading dot' => '.99',
    'explicit plus sign' => '+5',
    'exponent' => '1e3',
    'inner whitespace' => '1 000',
]);

it('formats as a decimal string with exactly 2 decimals', function (string $input, string $expected): void {
    expect(Money::fromDecimalString($input)->toDecimalString())->toBe($expected);
})->with([
    'zero' => ['0', '0.00'],
    'whole number' => ['22', '22.00'],
    'one decimal' => ['45.5', '45.50'],
    'two decimals' => ['89.99', '89.99'],
    'cents only' => ['0.07', '0.07'],
    'leading zeros' => ['007.10', '7.10'],
]);

it('rejects amounts too large to store as integer cents', function (string $input): void {
    expect(fn () => Money::fromDecimalString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('price');
    });
})->with([
    'whole part overflows' => '99999999999999999999',
    'cents overflow' => '92233720368547758.08',
]);

it('rebuilds money from persisted integer cents', function (): void {
    expect(Money::fromCents(8999)->toDecimalString())->toBe('89.99');
});

it('rejects negative persisted cents', function (): void {
    expect(fn () => Money::fromCents(-1))->toThrow(InvalidProductAttribute::class);
});
