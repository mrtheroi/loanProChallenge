<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\Stock;
use Ecommerce\Catalog\Domain\Weight;
use Ecommerce\Catalog\Infrastructure\CategoryModel;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function aPersistableProduct(
    string $sku = 'SKU-001',
    string $name = 'Wireless Mouse',
    ?string $description = 'Ergonomic mouse',
    string $category = 'Electronics',
    string $price = '29.99',
    int $stock = 10,
    string $weight = '0.125',
): Product {
    return Product::create(
        Sku::fromString($sku),
        ProductName::fromString($name),
        $description,
        CategoryName::fromString($category),
        Money::fromDecimalString($price),
        Stock::from($stock),
        Weight::fromOptionalDecimalString($weight),
    );
}

function productRepository(): ProductRepository
{
    return app(ProductRepository::class);
}

it('persists a new product and finds it by sku', function (): void {
    productRepository()->save(aPersistableProduct());

    $found = productRepository()->findBySku(Sku::fromString('SKU-001'));

    expect($found)->toEqual(aPersistableProduct());
});

it('round-trips a product without description or weight', function (): void {
    productRepository()->save(aPersistableProduct(description: null, weight: ''));

    expect(productRepository()->findBySku(Sku::fromString('SKU-001')))->toEqual(aPersistableProduct(description: null, weight: ''));
});

it('updates the stored product when saving an existing sku', function (): void {
    productRepository()->save(aPersistableProduct());
    productRepository()->save(aPersistableProduct(name: 'Wireless Mouse Pro', price: '39.50', stock: 3));

    expect(productRepository()->findBySku(Sku::fromString('SKU-001')))
        ->toEqual(aPersistableProduct(name: 'Wireless Mouse Pro', price: '39.50', stock: 3))
        ->and(ProductModel::query()->count())->toBe(1);
});

it('shares one category row across case and surrounding space differences', function (): void {
    productRepository()->save(aPersistableProduct(sku: 'SKU-001', category: 'Electronics'));
    productRepository()->save(aPersistableProduct(sku: 'SKU-002', category: ' electronics'));

    expect(CategoryModel::query()->count())->toBe(1)
        ->and(productRepository()->findBySku(Sku::fromString('SKU-002'))?->category()->value())->toBe('Electronics');
});

it('returns null for an unknown sku', function (): void {
    expect(productRepository()->findBySku(Sku::fromString('MISSING')))->toBeNull();
});

it('does not find a soft-deleted product', function (): void {
    productRepository()->save(aPersistableProduct());
    ProductModel::query()->where('sku', 'SKU-001')->firstOrFail()->delete();

    expect(productRepository()->findBySku(Sku::fromString('SKU-001')))->toBeNull()
        ->and(ProductModel::withTrashed()->where('sku', 'SKU-001')->exists())->toBeTrue();
});

it('keeps the sku of a soft-deleted product reserved at the database level', function (): void {
    productRepository()->save(aPersistableProduct());
    $deleted = ProductModel::query()->where('sku', 'SKU-001')->firstOrFail();
    $deleted->delete();

    expect(fn () => DB::table('products')->insert([
        'sku' => 'SKU-001',
        'name' => 'Another product',
        'category_id' => $deleted->category_id,
        'price_cents' => 100,
        'stock' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('round-trips the int32 maximum for stock and weight grams', function (): void {
    productRepository()->save(aPersistableProduct(stock: 2147483647, weight: '2147483.647'));

    $found = productRepository()->findBySku(Sku::fromString('SKU-001'));

    expect($found?->stock()->quantity())->toBe(2147483647)
        ->and($found?->weight()?->grams())->toBe(2147483647);
});
