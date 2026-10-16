<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

it('shows a product matched case-insensitively by sku', function (): void {
    $admin = AdminCatalog::admin();
    $this->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload())->assertCreated();

    $this->actingAs($admin)
        ->getJson('/api/admin/products/wm-001')
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
    $admin = AdminCatalog::admin();
    $this->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'GONE-1']))->assertCreated();
    app(ProductRepository::class)->delete(Sku::fromString('GONE-1'));

    $this->actingAs($admin)
        ->getJson('/api/admin/products/'.$sku)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Product not found.']);
})->with([
    'unknown' => ['NOPE-1'],
    'deleted' => ['gone-1'],
    'malformed' => ['bad%20sku'],
    'too long' => [str_repeat('A', 65)],
]);
