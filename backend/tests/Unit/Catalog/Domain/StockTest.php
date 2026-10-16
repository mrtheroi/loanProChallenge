<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Stock;

it('holds a non-negative integer quantity', function (int|string $input, int $expected): void {
    expect(Stock::from($input)->quantity())->toBe($expected);
})->with([
    'zero int' => [0, 0],
    'positive int' => [15, 15],
    'zero string' => ['0', 0],
    'numeric string' => ['42', 42],
    'padded numeric string' => [' 7 ', 7],
    'int32 maximum' => ['2147483647', 2147483647],
]);

it('rejects anything that is not a non-negative integer', function (int|string $input): void {
    expect(fn () => Stock::from($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('stock')
            ->and($e->reason())->not->toBeEmpty();
    });
})->with([
    'negative string' => '-5',
    'negative int' => -1,
    'decimal' => '1.5',
    'word' => 'abc',
    'empty' => '',
    'whitespace only' => '  ',
    'explicit plus sign' => '+5',
    'exponent' => '1e3',
    'too large' => '99999999999999999999',
]);

it('rejects quantities above the int32 column maximum', function (int|string $input): void {
    expect(fn () => Stock::from($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('stock')
            ->and($e->reason())->toBe('must be at most 2147483647');
    });
})->with([
    'int just above maximum' => 2147483648,
    'string just above maximum' => '2147483648',
]);
