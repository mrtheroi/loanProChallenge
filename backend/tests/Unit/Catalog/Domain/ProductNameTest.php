<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\ProductName;

it('keeps the trimmed value as-is', function (string $input, string $expected): void {
    expect(ProductName::fromString($input)->value())->toBe($expected);
})->with([
    'padded' => ['  Wireless Mouse  ', 'Wireless Mouse'],
    'xss payload' => ["<script>alert('xss')</script>", "<script>alert('xss')</script>"],
    'sql injection payload' => ["Robert'); DROP TABLE products;--", "Robert'); DROP TABLE products;--"],
    'unicode' => [' Café Crème ', 'Café Crème'],
]);

it('rejects blank input', function (string $input): void {
    expect(fn () => ProductName::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('name');
    });
})->with(['empty' => '', 'whitespace only' => "  \t\n "]);

it('rejects more than 255 characters', function (): void {
    expect(fn () => ProductName::fromString(str_repeat('n', 256)))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('name');
    });
});

it('accepts exactly 255 characters, counting multibyte characters once', function (string $input): void {
    expect(ProductName::fromString($input)->value())->toBe($input);
})->with(['ascii' => str_repeat('n', 255), 'multibyte' => str_repeat('ñ', 255)]);

it('rejects text that is not valid UTF-8 or contains control characters', function (string $input): void {
    expect(fn () => ProductName::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('name')
            ->and($e->reason())->toBe('must be valid UTF-8 text without control characters');
    });
})->with(['latin-1' => "Caf\xE9", 'nul byte' => "a\0b", 'line feed' => "a\nb", 'delete' => "a\x7Fb", 'c1 next line' => "a\u{0085}b"]);

it('accepts ordinary text with accented letters', function (): void {
    expect(ProductName::fromString('Café crème àéîõü')->value())->toBe('Café crème àéîõü');
});
