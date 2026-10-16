<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\SkuIsReserved;
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

it('refuses to save the sku of a soft-deleted product and leaves the deleted row untouched', function (): void {
    productRepository()->save(aPersistableProduct());
    ProductModel::query()->where('sku', 'SKU-001')->firstOrFail()->delete();
    $before = ProductModel::withTrashed()->where('sku', 'SKU-001')->firstOrFail()->getAttributes();

    expect(fn () => productRepository()->save(aPersistableProduct(name: 'Replacement', price: '1.00')))
        ->toThrow(SkuIsReserved::class, 'SKU-001');

    expect(ProductModel::withTrashed()->where('sku', 'SKU-001')->firstOrFail()->getAttributes())->toBe($before)
        ->and(ProductModel::withTrashed()->count())->toBe(1);
});

it('reports a reserved sku when a concurrent insert takes the sku first', function (): void {
    ProductModel::creating(function (ProductModel $product): void {
        DB::table('products')->insert([
            'sku' => $product->sku,
            'name' => 'Concurrent product',
            'category_id' => $product->category_id,
            'price_cents' => 100,
            'stock' => 1,
        ]);
    });

    expect(fn () => productRepository()->save(aPersistableProduct()))->toThrow(SkuIsReserved::class, 'SKU-001')
        ->and(ProductModel::withTrashed()->count())->toBe(0);
});

it('round-trips every field at its maximum allowed value', function (): void {
    $product = Product::create(
        Sku::fromString(str_repeat('S', 64)),
        ProductName::fromString(str_repeat('ñ', 255)),
        str_repeat('Long description. ', 5000),
        CategoryName::fromString(str_repeat('É', 100)),
        Money::fromCents(PHP_INT_MAX),
        Stock::from(2147483647),
        Weight::fromGrams(2147483647),
    );

    productRepository()->save($product);

    expect(productRepository()->findBySku($product->sku()))->toEqual($product);
});

it('casts numeric product columns to integers regardless of the driver representation', function (): void {
    $model = (new ProductModel)->setRawAttributes(['price_cents' => '9223372036854775807', 'stock' => '7', 'weight_grams' => '125']);

    expect($model->price_cents)->toBe(PHP_INT_MAX)
        ->and($model->stock)->toBe(7)
        ->and($model->weight_grams)->toBe(125);
});
