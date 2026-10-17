<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

/** @param  array<int, array<string, mixed>>  $overrides */
function seedProducts(array $overrides): void
{
    $admin = AdminCatalog::admin();

    foreach ($overrides as $override) {
        test()->actingAs($admin)->postJson('/api/admin/products', AdminCatalog::payload($override))->assertCreated();
    }

    auth()->guard('web')->logout();
}

it('lets a guest list products sorted by name with pagination metadata', function (): void {
    seedProducts([
        ['sku' => 'KB-001', 'name' => 'Keyboard'],
        ['sku' => 'WM-001', 'name' => 'Wireless Mouse'],
        ['sku' => 'AD-001', 'name' => 'Adapter', 'weight_kg' => null, 'description' => null],
    ]);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['sku' => 'AD-001', 'name' => 'Adapter', 'description' => null, 'category' => 'Electronics', 'price' => '89.99', 'stock' => 12, 'weight_kg' => null],
                ['sku' => 'KB-001', 'name' => 'Keyboard', 'description' => 'Ergonomic mouse', 'category' => 'Electronics', 'price' => '89.99', 'stock' => 12, 'weight_kg' => '0.350'],
                ['sku' => 'WM-001', 'name' => 'Wireless Mouse', 'description' => 'Ergonomic mouse', 'category' => 'Electronics', 'price' => '89.99', 'stock' => 12, 'weight_kg' => '0.350'],
            ],
            'meta' => ['current_page' => 1, 'per_page' => 12, 'total' => 3, 'last_page' => 1],
        ]);
});

/** @return list<string> */
function searchSkus(string $query): array
{
    return array_column(test()->getJson('/api/products'.$query)->assertOk()->json('data'), 'sku');
}

it('hides soft deleted products', function (): void {
    seedProducts([['sku' => 'KB-001', 'name' => 'Keyboard'], ['sku' => 'GONE-1', 'name' => 'Gone']]);
    app(ProductRepository::class)->delete(Sku::fromString('GONE-1'));

    expect(searchSkus(''))->toBe(['KB-001']);
});

it('sorts names case-insensitively', function (): void {
    seedProducts([['sku' => 'B-1', 'name' => 'banana'], ['sku' => 'A-1', 'name' => 'Apple'], ['sku' => 'C-1', 'name' => 'Cherry']]);

    expect(searchSkus(''))->toBe(['A-1', 'B-1', 'C-1']);
});

it('matches free text partially and case-insensitively in name, sku or description', function (string $text, array $expected): void {
    seedProducts([
        ['sku' => 'KB-001', 'name' => 'Mechanical Keyboard', 'description' => 'Blue switches'],
        ['sku' => 'WM-001', 'name' => 'Wireless Mouse', 'description' => 'Ergonomic grip'],
        ['sku' => 'CB-777', 'name' => 'Cable', 'description' => null],
    ]);

    expect(searchSkus('?q='.urlencode($text)))->toBe($expected);
})->with([
    'name' => ['keyb', ['KB-001']],
    'sku' => ['cb-7', ['CB-777']],
    'description' => ['ERGONOMIC', ['WM-001']],
    'surrounding spaces are ignored' => ['  mouse ', ['WM-001']],
    'no match' => ['monitor', []],
]);

it('treats like wildcards in the free text literally', function (string $text, array $expected): void {
    seedProducts([
        ['sku' => 'P-100', 'name' => '100% Cotton Shirt'],
        ['sku' => 'P-200', 'name' => '1000 Piece Puzzle'],
        ['sku' => 'P_300', 'name' => 'Underscore item'],
        ['sku' => 'P-400', 'name' => 'Back\\slash item'],
    ]);

    expect(searchSkus('?q='.urlencode($text)))->toBe($expected);
})->with([
    'percent' => ['100%', ['P-100']],
    'underscore' => ['p_', ['P_300']],
    'backslash' => ['k\\s', ['P-400']],
]);

it('rejects free text that is not plain text of at most 100 characters', function (string $query): void {
    $this->getJson('/api/products?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
})->with([
    'array' => ['q[]=a'],
    'nul byte' => ['q=a%00b'],
    'invalid utf-8' => ['q=Caf%E9'],
    'too long' => ['q='.str_repeat('a', 101)],
]);

it('accepts free text of exactly 100 characters', function (): void {
    $this->getJson('/api/products?q='.str_repeat('a', 100))->assertOk();
});

it('filters by category using the normalized name', function (string $category, array $expected): void {
    seedProducts([
        ['sku' => 'KB-001', 'name' => 'Keyboard', 'category' => 'Home Office'],
        ['sku' => 'WM-001', 'name' => 'Mouse', 'category' => 'Electronics'],
    ]);

    expect(searchSkus('?category='.urlencode($category)))->toBe($expected);
})->with([
    'exact' => ['Home Office', ['KB-001']],
    'casing and spacing differ' => ['  home   OFFICE ', ['KB-001']],
    'unknown category' => ['Garden', []],
    'partial name does not match' => ['Home', []],
]);

it('rejects an invalid category filter', function (string $query): void {
    $this->getJson('/api/products?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);
})->with([
    'array' => ['category[]=a'],
    'nul byte' => ['category=a%00b'],
    'too long' => ['category='.str_repeat('a', 101)],
]);

it('filters by an inclusive price range', function (string $query, array $expected): void {
    seedProducts([
        ['sku' => 'A-1', 'name' => 'A', 'price' => '0'],
        ['sku' => 'B-1', 'name' => 'B', 'price' => '10.00'],
        ['sku' => 'C-1', 'name' => 'C', 'price' => '25.50'],
    ]);

    expect(searchSkus('?'.$query))->toBe($expected);
})->with([
    'minimum only' => ['min_price=10', ['B-1', 'C-1']],
    'maximum only' => ['max_price=10.00', ['A-1', 'B-1']],
    'both bounds' => ['min_price=0.01&max_price=25.49', ['B-1']],
    'equal bounds' => ['min_price=25.5&max_price=25.5', ['C-1']],
]);

it('rejects a price bound that is not a valid price', function (string $field, string $value): void {
    $this->getJson("/api/products?{$field}=".urlencode($value))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'dollar sign' => ['min_price', '$5'],
    'word' => ['max_price', 'free'],
    'negative' => ['min_price', '-1'],
    'three decimals' => ['max_price', '1.234'],
]);

it('rejects a minimum price greater than the maximum price', function (): void {
    $this->getJson('/api/products?min_price=20&max_price=10')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['min_price']);
});

it('filters to products in stock only when asked', function (string $query, array $expected): void {
    seedProducts([['sku' => 'A-1', 'name' => 'A', 'stock' => 0], ['sku' => 'B-1', 'name' => 'B', 'stock' => 1]]);

    expect(searchSkus($query))->toBe($expected);
})->with([
    'true' => ['?in_stock=true', ['B-1']],
    'one' => ['?in_stock=1', ['B-1']],
    'false shows every product' => ['?in_stock=false', ['A-1', 'B-1']],
    'zero shows every product' => ['?in_stock=0', ['A-1', 'B-1']],
    'absent shows every product' => ['', ['A-1', 'B-1']],
]);

it('rejects an in stock flag that is not a boolean', function (): void {
    $this->getJson('/api/products?in_stock=yes')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['in_stock']);
});

it('sorts by name or price in either direction with the sku as tie-breaker', function (string $sort, array $expected): void {
    seedProducts([
        ['sku' => 'B-2', 'name' => 'Bolt', 'price' => '5.00'],
        ['sku' => 'B-1', 'name' => 'Bolt', 'price' => '5.00'],
        ['sku' => 'A-1', 'name' => 'Anchor', 'price' => '9.00'],
        ['sku' => 'C-1', 'name' => 'Clamp', 'price' => '1.00'],
    ]);

    expect(searchSkus('?sort='.$sort))->toBe($expected);
})->with([
    'name ascending' => ['name', ['A-1', 'B-1', 'B-2', 'C-1']],
    'name descending' => ['-name', ['C-1', 'B-1', 'B-2', 'A-1']],
    'price ascending' => ['price', ['C-1', 'B-1', 'B-2', 'A-1']],
    'price descending' => ['-price', ['A-1', 'B-1', 'B-2', 'C-1']],
]);

it('rejects an unknown sort', function (string $sort): void {
    $this->getJson('/api/products?sort='.$sort)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
})->with(['stock', 'NAME', 'name;drop table products']);

it('paginates with page and per page', function (string $query, array $expectedSkus, array $expectedMeta): void {
    seedProducts([
        ['sku' => 'A-1', 'name' => 'A'],
        ['sku' => 'B-1', 'name' => 'B'],
        ['sku' => 'C-1', 'name' => 'C'],
    ]);

    $response = $this->getJson('/api/products?'.$query)->assertOk();

    expect(array_column($response->json('data'), 'sku'))->toBe($expectedSkus)
        ->and($response->json('meta'))->toBe($expectedMeta);
})->with([
    'first page' => ['per_page=2', ['A-1', 'B-1'], ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2]],
    'second page' => ['per_page=2&page=2', ['C-1'], ['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]],
    'past the last page' => ['per_page=2&page=5', [], ['current_page' => 5, 'per_page' => 2, 'total' => 3, 'last_page' => 2]],
    'maximum per page' => ['per_page=50', ['A-1', 'B-1', 'C-1'], ['current_page' => 1, 'per_page' => 50, 'total' => 3, 'last_page' => 1]],
]);

it('reports one empty page when nothing matches', function (): void {
    $this->getJson('/api/products?q=nothing')
        ->assertOk()
        ->assertExactJson(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 12, 'total' => 0, 'last_page' => 1]]);
});

it('rejects invalid pagination values', function (string $field, string $value): void {
    $this->getJson("/api/products?{$field}={$value}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'page zero' => ['page', '0'],
    'page not an integer' => ['page', '1.5'],
    'page too large' => ['page', '1000001'],
    'page beyond the integer range' => ['page', '9223372036854775808'],
    'per page zero' => ['per_page', '0'],
    'per page above the maximum' => ['per_page', '51'],
    'per page not an integer' => ['per_page', 'ten'],
]);

it('serves the same search to signed in admins and buyers', function (string $role): void {
    seedProducts([['sku' => 'KB-001', 'name' => 'Keyboard']]);
    $user = $role === 'admin' ? AdminCatalog::admin() : AdminCatalog::buyer();

    $this->actingAs($user)->getJson('/api/products')->assertOk()->assertJsonPath('data.0.sku', 'KB-001');
})->with(['admin', 'buyer']);
