<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\Stock;
use Ecommerce\Catalog\Domain\Weight;

function aProduct(?string $description = 'Ergonomic mouse', ?Weight $weight = null): Product
{
    return Product::create(
        Sku::fromString('MS-001'),
        ProductName::fromString('Wireless Mouse'),
        $description,
        CategoryName::fromString('Electronics'),
        Money::fromDecimalString('29.99'),
        Stock::from(15),
        $weight,
    );
}

it('exposes the attributes it was created with', function (): void {
    $weight = Weight::fromDecimalString('0.35');
    $product = aProduct(weight: $weight);

    expect($product->sku()->value())->toBe('MS-001')
        ->and($product->name()->value())->toBe('Wireless Mouse')
        ->and($product->description())->toBe('Ergonomic mouse')
        ->and($product->category()->value())->toBe('Electronics')
        ->and($product->price()->cents())->toBe(2999)
        ->and($product->stock()->quantity())->toBe(15)
        ->and($product->weight())->toBe($weight);
});

it('trims the description and stores blank descriptions as null', function (?string $input, ?string $expected): void {
    expect(aProduct(description: $input)->description())->toBe($expected);
})->with([
    'padded' => ['  Ergonomic mouse  ', 'Ergonomic mouse'],
    'empty' => ['', null],
    'whitespace only' => ["  \n ", null],
    'absent' => [null, null],
]);

it('allows a product without weight', function (): void {
    expect(aProduct(weight: null)->weight())->toBeNull();
});
