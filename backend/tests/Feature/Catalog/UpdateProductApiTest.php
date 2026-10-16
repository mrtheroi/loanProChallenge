<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = AdminCatalog::admin();
    $this->actingAs($this->admin)->postJson('/api/admin/products', AdminCatalog::payload())->assertCreated();
});

function updatedProductPayload(array $overrides = []): array
{
    return AdminCatalog::payload(array_merge([
        'name' => 'Wireless Mouse Pro',
        'description' => null,
        'category' => 'Accessories',
        'price' => '99.50',
        'stock' => '4',
        'weight_kg' => '0.400',
    ], $overrides));
}

it('replaces every editable field, including the name', function (): void {
    $this->actingAs($this->admin)
        ->putJson('/api/admin/products/wm-001', updatedProductPayload())
        ->assertOk()
        ->assertExactJson(['data' => [
            'sku' => 'WM-001',
            'name' => 'Wireless Mouse Pro',
            'description' => null,
            'category' => 'Accessories',
            'price' => '99.50',
            'stock' => 4,
            'weight_kg' => '0.400',
        ]]);

    $stored = app(ProductRepository::class)->findBySku(Sku::fromString('WM-001'));
    expect($stored?->name()->value())->toBe('Wireless Mouse Pro')
        ->and($stored?->category()->value())->toBe('Accessories')
        ->and($stored?->price()->cents())->toBe(9950)
        ->and($stored?->stock()->quantity())->toBe(4)
        ->and($stored?->weight()?->grams())->toBe(400)
        ->and($stored?->description())->toBeNull()
        ->and(ProductModel::withTrashed()->count())->toBe(1);
});

it('refuses to change the sku and leaves the product untouched', function (string $bodySku): void {
    $before = ProductModel::withTrashed()->get()->toArray();

    $this->actingAs($this->admin)
        ->putJson('/api/admin/products/WM-001', updatedProductPayload(['sku' => $bodySku]))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'must match the sku of the product being updated',
            'errors' => ['sku' => ['must match the sku of the product being updated']],
        ]);

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($before);
})->with(['other sku' => ['OTHER-1'], 'other sku in lowercase' => ['wm-002']]);

it('takes the sku from the path when the body omits it', function (): void {
    $payload = updatedProductPayload();
    unset($payload['sku']);

    $this->actingAs($this->admin)
        ->putJson('/api/admin/products/wm-001', $payload)
        ->assertOk()
        ->assertJsonPath('data.sku', 'WM-001')
        ->assertJsonPath('data.name', 'Wireless Mouse Pro');

    expect(ProductModel::withTrashed()->sole()->name)->toBe('Wireless Mouse Pro');
});

it('returns 404 when updating an unknown, deleted or malformed sku', function (string $sku): void {
    app(ProductRepository::class)->delete(Sku::fromString('WM-001'));
    $before = ProductModel::withTrashed()->get()->toArray();

    $this->actingAs($this->admin)
        ->putJson('/api/admin/products/'.$sku, updatedProductPayload(['sku' => $sku]))
        ->assertNotFound();

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($before);
})->with(['unknown' => ['NOPE-1'], 'deleted' => ['WM-001'], 'malformed' => ['bad%20sku']]);

it('rejects invalid content and wrong json types on update without changing the product', function (string $field, mixed $value): void {
    $before = ProductModel::withTrashed()->get()->toArray();

    $this->actingAs($this->admin)
        ->putJson('/api/admin/products/WM-001', updatedProductPayload([$field => $value]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([$field]);

    expect(ProductModel::withTrashed()->get()->toArray())->toBe($before);
})->with([
    'price as a json number' => ['price', 89.99],
    'price as a word' => ['price', 'free'],
    'name as an array' => ['name', ['a']],
    'blank name' => ['name', '   '],
    'negative stock string' => ['stock', '-3'],
    'sku as null' => ['sku', null],
]);
