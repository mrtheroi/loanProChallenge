<?php

declare(strict_types=1);

use Ecommerce\Catalog\Application\ProductData;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(ProductRepository::class)->add((new ProductData('EXISTING-1', 'Existing', null, 'Tools', '5.00', 3, null))->toProduct());
    $this->storedProducts = ProductModel::withTrashed()->get()->toArray();
});

dataset('admin product endpoints', [
    'create' => ['POST', '/api/admin/products'],
    'show' => ['GET', '/api/admin/products/EXISTING-1'],
    'update' => ['PUT', '/api/admin/products/EXISTING-1'],
    'delete' => ['DELETE', '/api/admin/products/EXISTING-1'],
]);

it('rejects guests with 401 on every admin product endpoint', function (string $method, string $uri): void {
    $this->json($method, $uri, AdminCatalog::payload(['sku' => 'EXISTING-1']))
        ->assertUnauthorized()
        ->assertJsonMissingPath('data');

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($this->storedProducts);
})->with('admin product endpoints');

it('forbids buyers with 403 on every admin product endpoint', function (string $method, string $uri): void {
    $this->actingAs(AdminCatalog::buyer())
        ->json($method, $uri, AdminCatalog::payload(['sku' => 'EXISTING-1', 'name' => 'Hijacked']))
        ->assertForbidden()
        ->assertJsonMissingPath('data');

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($this->storedProducts);
})->with('admin product endpoints');
