<?php

declare(strict_types=1);

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = AdminCatalog::admin();
    $this->actingAs($this->admin)->postJson('/api/admin/products', AdminCatalog::payload())->assertCreated();
});

it('soft deletes a product matched case-insensitively by sku', function (): void {
    $this->actingAs($this->admin)
        ->deleteJson('/api/admin/products/wm-001')
        ->assertNoContent();

    expect(ProductModel::query()->count())->toBe(0)
        ->and(ProductModel::onlyTrashed()->sole()->sku)->toBe('WM-001');
    $this->actingAs($this->admin)->getJson('/api/admin/products/WM-001')->assertNotFound();
});

it('returns 404 when deleting an unknown, already deleted or malformed sku', function (string $sku): void {
    $this->actingAs($this->admin)->deleteJson('/api/admin/products/WM-001')->assertNoContent();
    $before = ProductModel::withTrashed()->get()->toArray();

    $this->actingAs($this->admin)
        ->deleteJson('/api/admin/products/'.$sku)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Product not found.']);

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($before);
})->with(['unknown' => ['NOPE-1'], 'already deleted' => ['wm-001'], 'malformed' => ['bad%20sku']]);

it('keeps the sku of a deleted product reserved for new products', function (): void {
    $this->actingAs($this->admin)->deleteJson('/api/admin/products/WM-001')->assertNoContent();

    $this->actingAs($this->admin)
        ->postJson('/api/admin/products', AdminCatalog::payload(['sku' => 'wm-001', 'name' => 'Comeback']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.sku', ['belongs to a deleted product and cannot be reused']);

    expect(ProductModel::withTrashed()->sole()->name)->toBe('Wireless Mouse');
});
