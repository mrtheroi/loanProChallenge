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
})->with(['ascii' => str_repeat('A', 64), 'multibyte' => str_repeat('É', 64)]);

it('compares canonical values', function (string $other, bool $expected): void {
    expect(Sku::fromString('KB-001')->equals(Sku::fromString($other)))->toBe($expected);
})->with([
    'same value' => ['KB-001', true],
    'same value after trimming' => [' KB-001 ', true],
    'different case' => ['kb-001', true],
    'different value' => ['KB-002', false],
]);

it('rejects text that is not valid UTF-8 or contains control characters', function (string $input): void {
    expect(fn () => Sku::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('sku')
            ->and($e->reason())->toBe('must be valid UTF-8 text without control characters');
    });
})->with(['latin-1' => "Caf\xE9", 'nul byte' => "a\0b", 'tab' => "a\tb"]);

it('stores the sku in uppercase after trimming', function (): void {
    expect(Sku::fromString('  sku-001 ')->value())->toBe('SKU-001');
});

it('treats skus that differ only in casing as equal', function (): void {
    expect(Sku::fromString('rs-001')->equals(Sku::fromString('RS-001')))->toBeTrue();
});

it('rejects a sku whose uppercase form exceeds 64 characters', function (): void {
    expect(fn () => Sku::fromString(str_repeat('ß', 40)))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('sku')
            ->and($e->reason())->toBe('must not exceed 64 characters');
    });
});
