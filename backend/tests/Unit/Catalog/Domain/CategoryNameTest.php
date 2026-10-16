<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\InvalidProductAttribute;

it('keeps the trimmed value with its original casing', function (): void {
    expect(CategoryName::fromString(' Home & Kitchen ')->value())->toBe('Home & Kitchen');
});

it('rejects blank or overlong input', function (string $input): void {
    expect(fn () => CategoryName::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('category');
    });
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'more than 100 characters' => str_repeat('c', 101),
]);

it('accepts exactly 100 characters', function (): void {
    expect(CategoryName::fromString(str_repeat('c', 100))->value())->toBe(str_repeat('c', 100));
});

it('normalizes to the lowercase trimmed form', function (string $input, string $expected): void {
    expect(CategoryName::fromString($input)->normalized())->toBe($expected);
})->with([
    'trailing space' => ['Electronics ', 'electronics'],
    'already lowercase' => ['electronics', 'electronics'],
    'multibyte uppercase' => [' ÉLECTRONIQUE ', 'électronique'],
]);

it('rejects a name whose lowercase form exceeds 100 characters', function (): void {
    expect(fn () => CategoryName::fromString(str_repeat('İ', 100)))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('category');
    });
});

it('accepts a name whose lowercase form is exactly 100 characters', function (): void {
    expect(mb_strlen(CategoryName::fromString(str_repeat('İ', 50))->normalized()))->toBe(100);
});

it('rejects text that is not valid UTF-8 or contains control characters', function (string $input): void {
    expect(fn () => CategoryName::fromString($input))->toThrow(function (InvalidProductAttribute $e): void {
        expect($e->field())->toBe('category')
            ->and($e->reason())->toBe('must be valid UTF-8 text without control characters');
    });
})->with(['latin-1' => "Caf\xE9", 'nul byte' => "a\0b", 'carriage return' => "a\rb"]);

it('trims Unicode whitespace such as a no-break space', function (): void {
    expect(CategoryName::fromString("Electronics\u{00A0}")->value())->toBe('Electronics');
});

it('stores the composed NFC form so decomposed and composed names match', function (): void {
    $decomposed = CategoryName::fromString("Cafe\u{301}");
    $composed = CategoryName::fromString("Caf\u{E9}");

    expect($decomposed->value())->toBe("Caf\u{E9}")
        ->and($decomposed->normalized())->toBe($composed->normalized());
});

it('collapses internal whitespace runs when normalizing', function (): void {
    expect(CategoryName::fromString('Home  Garden')->normalized())
        ->toBe(CategoryName::fromString('Home Garden')->normalized());
});
