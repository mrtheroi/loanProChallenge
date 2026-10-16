<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

it('creates a product and returns it with a normalized sku', function (): void {
    $this->actingAs(AdminCatalog::admin())
        ->postJson('/api/admin/products', AdminCatalog::payload())
        ->assertCreated()
        ->assertExactJson(['data' => [
            'sku' => 'WM-001',
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic mouse',
            'category' => 'Electronics',
            'price' => '89.99',
            'stock' => 12,
            'weight_kg' => '0.350',
        ]]);

    expect(app(ProductRepository::class)->findBySku(Sku::fromString('WM-001'))?->name()->value())->toBe('Wireless Mouse');
});

it('rejects content the domain refuses with a 422 on the offending field', function (string $field, mixed $value, string $reason): void {
    $this->actingAs(AdminCatalog::admin())
        ->postJson('/api/admin/products', AdminCatalog::payload([$field => $value]))
        ->assertUnprocessable()
        ->assertExactJson(['message' => $reason, 'errors' => [$field => [$reason]]]);

    expect(ProductModel::withTrashed()->count())->toBe(0);
})->with([
    'price with a currency sign' => ['price', '$10', 'must be a non-negative decimal number with at most 2 decimals'],
    'price as a word' => ['price', 'free', 'must be a non-negative decimal number with at most 2 decimals'],
    'negative stock' => ['stock', -1, 'must be a non-negative integer'],
    'stock as a non-numeric string' => ['stock', 'abc', 'must be a non-negative integer'],
    'stock as a decimal string' => ['stock', '1.5', 'must be a non-negative integer'],
    'sku with spaces' => ['sku', 'WM 001', 'must contain only letters A-Z, digits, dashes, underscores and dots'],
    'too long name' => ['name', str_repeat('a', 256), 'must not exceed 255 characters'],
    'price beyond the integer range' => ['price', '99999999999999999999', 'is too large'],
    'description with a nul byte' => ['description', "a\u{0}b", 'must be valid UTF-8 text without control characters'],
    'weight with four decimals' => ['weight_kg', '0.1234', 'must be a non-negative decimal number with at most 3 decimals'],
]);

it('rejects missing fields and values of the wrong json type with a 422', function (string $field, mixed $value): void {
    $this->actingAs(AdminCatalog::admin())
        ->postJson('/api/admin/products', AdminCatalog::payload([$field => $value]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);

    expect(ProductModel::withTrashed()->count())->toBe(0);
})->with([
    'price as a json number' => ['price', 89.99],
    'weight as a json number' => ['weight_kg', 0.35],
    'name as an array' => ['name', ['a']],
    'sku as a number' => ['sku', 123],
    'category as an object' => ['category', ['x' => 'y']],
    'description as an array' => ['description', ['a']],
    'stock as a float' => ['stock', 1.5],
    'stock as a boolean' => ['stock', true],
    'stock as an array' => ['stock', [1]],
    'missing name' => ['name', null],
    'missing sku' => ['sku', null],
    'missing category' => ['category', null],
    'missing price' => ['price', null],
    'missing stock' => ['stock', null],
]);

it('accepts an integer string stock and stores omitted optional fields as null', function (): void {
    $this->actingAs(AdminCatalog::admin())
        ->postJson('/api/admin/products', AdminCatalog::payload(['stock' => '7', 'description' => null, 'weight_kg' => null]))
        ->assertCreated()
        ->assertJsonPath('data.stock', 7)
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.weight_kg', null);

    $stored = ProductModel::query()->sole();
    expect($stored->stock)->toBe(7)
        ->and($stored->description)->toBeNull()
        ->and($stored->weight_grams)->toBeNull();
});

it('stores hostile names from the sample csv exactly as sent', function (string $name): void {
    $this->actingAs(AdminCatalog::admin())
        ->postJson('/api/admin/products', AdminCatalog::payload(['name' => $name]))
        ->assertCreated()
        ->assertJsonPath('data.name', $name);

    expect(ProductModel::query()->sole()->name)->toBe($name)
        ->and(ProductModel::query()->count())->toBe(1);
})->with([
    'xss' => ["<script>alert('xss')</script>"],
    'sql injection' => ["Robert'); DROP TABLE products;--"],
]);

it('refuses to create a product whose sku already exists, in any casing', function (): void {
    $admin = AdminCatalog::admin();
    $this->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload())->assertCreated();

    $this->actingAs($admin)
        ->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'WM-001', 'name' => 'Intruder']))
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'already exists', 'errors' => ['sku' => ['already exists']]]);

    expect(ProductModel::query()->sole()->name)->toBe('Wireless Mouse');
});

it('refuses to create a product with the sku of a deleted product', function (): void {
    $admin = AdminCatalog::admin();
    $this->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload())->assertCreated();
    app(ProductRepository::class)->delete(Sku::fromString('WM-001'));

    $this->actingAs($admin)
        ->postJson('/api/admin/products', AdminCatalog::payload(['name' => 'Replacement']))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'belongs to a deleted product and cannot be reused',
            'errors' => ['sku' => ['belongs to a deleted product and cannot be reused']],
        ]);

    expect(ProductModel::withTrashed()->sole()->name)->toBe('Wireless Mouse');
});
