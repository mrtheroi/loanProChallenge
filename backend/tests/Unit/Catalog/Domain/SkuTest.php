<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Sku;

it('keeps the trimmed value', function (): void {
    expect(Sku::fromString('  KB-001 ')->value())->toBe('KB-001');
});

it('rejects blank input', function (string $input): void {
    expect(fn () => Sku::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('sku')
            ->and($e->reason())->not->toBeEmpty();
    });
})->with(['empty' => '', 'whitespace only' => "  \t "]);

it('rejects more than 64 characters', function (): void {
    expect(fn () => Sku::fromString(str_repeat('A', 65)))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('sku');
    });
});

it('accepts exactly 64 characters, counting multibyte characters once', function (string $input): void {
    expect(Sku::fromString($input)->value())->toBe($input);
})->with(['ascii' => str_repeat('A', 64), 'multibyte' => str_repeat('é', 64)]);

it('compares exactly and case-sensitively', function (string $other, bool $expected): void {
    expect(Sku::fromString('KB-001')->equals(Sku::fromString($other)))->toBe($expected);
})->with([
    'same value' => ['KB-001', true],
    'same value after trimming' => [' KB-001 ', true],
    'different case' => ['kb-001', false],
    'different value' => ['KB-002', false],
]);
