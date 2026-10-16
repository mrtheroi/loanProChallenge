<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Weight;

it('stores kilograms as integer grams', function (string $input, int $grams): void {
    expect(Weight::fromDecimalString($input)->grams())->toBe($grams);
})->with([
    'zero' => ['0', 0],
    'one decimal' => ['35.0', 35000],
    'two decimals' => ['0.35', 350],
    'small fraction' => ['0.03', 30],
    'three decimals' => ['1.234', 1234],
    'surrounding whitespace' => [' 2 ', 2000],
]);

it('rejects anything that is not a non-negative decimal with at most 3 decimals', function (string $input): void {
    expect(fn () => Weight::fromDecimalString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('weight_kg')
            ->and($e->reason())->not->toBeEmpty();
    });
})->with([
    'negative' => '-0.5',
    'word' => 'heavy',
    'unit suffix' => '2kg',
    'more than 3 decimals' => '0.1234',
    'empty' => '',
    'decimal comma' => '0,35',
    'too large' => '99999999999999999999',
]);

it('formats as kilograms with exactly 3 decimals', function (string $input, string $expected): void {
    expect(Weight::fromDecimalString($input)->toKilogramString())->toBe($expected);
})->with([
    'zero' => ['0', '0.000'],
    'one decimal' => ['35.0', '35.000'],
    'two decimals' => ['0.35', '0.350'],
    'small fraction' => ['0.03', '0.030'],
    'three decimals' => ['1.234', '1.234'],
]);

it('treats blank optional input as no weight', function (string $input): void {
    expect(Weight::fromOptionalDecimalString($input))->toBeNull();
})->with(['empty' => '', 'whitespace only' => "  \t "]);

it('builds a weight from non-blank optional input', function (): void {
    expect(Weight::fromOptionalDecimalString(' 0.35 ')?->grams())->toBe(350);
});

it('still rejects invalid non-blank optional input', function (): void {
    expect(fn () => Weight::fromOptionalDecimalString('-1'))->toThrow(InvalidProductAttribute::class);
});
