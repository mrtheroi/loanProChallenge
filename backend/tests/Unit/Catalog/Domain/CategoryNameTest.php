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
