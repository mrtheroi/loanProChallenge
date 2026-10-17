<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Infrastructure\CategoryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

function seedCatalog(): void
{
    $admin = AdminCatalog::admin();
    test()->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'WM-001', 'category' => 'Electronics']))->assertCreated();
    test()->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'MUG-1', 'name' => 'Mug', 'category' => 'kitchen']))->assertCreated();
    test()->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'GONE-1', 'name' => 'Gone', 'category' => 'Garden']))->assertCreated();
    app(ProductRepository::class)->delete(Sku::fromString('GONE-1'));
    auth()->guard('web')->logout();
}

it('lets a guest see one product by sku, case-insensitively', function (): void {
    seedCatalog();

    $this->getJson('/api/products/wm-001')
        ->assertOk()
        ->assertExactJson(['data' => [
            'sku' => 'WM-001',
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic mouse',
            'category' => 'Electronics',
            'price' => '89.99',
            'stock' => 12,
            'weight_kg' => '0.350',
        ]]);
});

it('returns 404 for an unknown, deleted or malformed sku', function (string $sku): void {
    seedCatalog();

    $this->getJson('/api/products/'.$sku)->assertNotFound()->assertExactJson(['message' => 'Product not found.']);
})->with(['unknown' => ['NOPE-1'], 'deleted' => ['GONE-1'], 'malformed' => ['bad%20sku']]);

it('lists the categories that have visible products, sorted by name', function (): void {
    seedCatalog();
    CategoryModel::query()->create(['name' => 'Empty', 'normalized_name' => 'empty']);

    $this->getJson('/api/categories')
        ->assertOk()
        ->assertExactJson(['data' => ['Electronics', 'kitchen']]);
});
