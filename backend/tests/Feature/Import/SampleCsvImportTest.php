<?php

declare(strict_types=1);

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

/** @return TestResponse<JsonResponse> */
function importSampleCsv(): TestResponse
{
    $contents = (string) file_get_contents(base_path('tests/Fixtures/sample-products.csv'));

    return test()->actingAs(AdminCatalog::admin())->post(
        '/api/admin/products/import',
        ['file' => UploadedFile::fake()->createWithContent('products.csv', $contents)],
        ['Accept' => 'application/json'],
    )->assertOk();
}

/**
 * @param  TestResponse<JsonResponse>  $response
 * @return array<int, array<string, mixed>>
 */
function rowsNotWithStatus(TestResponse $response, string $status): array
{
    $rows = array_filter((array) $response->json('rows'), fn (array $row): bool => $row['status'] !== $status);

    return array_combine(array_column($rows, 'row'), array_map(fn (array $row): array => $row['errors'], $rows));
}

it('reports every trap of the sample csv and imports the rest', function (): void {
    $response = importSampleCsv();

    $response->assertJsonPath('summary', ['created' => 84, 'updated' => 0, 'unchanged' => 0, 'failed' => 11, 'blank_rows' => 2]);
    $invalidPrice = ['price' => ['must be a non-negative decimal number with at most 2 decimals']];
    expect(rowsNotWithStatus($response, 'created'))->toBe([
        2 => ['sku' => ['appears in rows 2, 36 with different data']],
        4 => $invalidPrice,
        7 => $invalidPrice,
        11 => ['sku' => ['appears in rows 11, 56, 89 with different data']],
        16 => ['stock' => ['must be a non-negative integer']],
        25 => ['name' => ['is required']],
        36 => ['sku' => ['appears in rows 2, 36 with different data']],
        41 => ['name' => ['is required']],
        52 => ['category' => ['is required']],
        56 => ['sku' => ['appears in rows 11, 56, 89 with different data']],
        89 => ['sku' => ['appears in rows 11, 56, 89 with different data']],
    ]);

    expect(ProductModel::query()->count())->toBe(84)
        ->and(ProductModel::query()->where('sku', 'XS-001')->value('name'))->toBe("<script>alert('xss')</script>")
        ->and(ProductModel::query()->where('sku', 'SQL-001')->value('name'))->toBe("Robert'); DROP TABLE products;--")
        ->and(ProductModel::query()->where('sku', 'QI-001')->value('name'))->toBe('Quote "Inside" Name')
        ->and(ProductModel::query()->where('sku', 'GK-088')->value('weight_grams'))->toBeNull();
});

it('reports the valid rows as unchanged when the same file is imported again', function (): void {
    importSampleCsv();

    importSampleCsv()
        ->assertJsonPath('summary', ['created' => 0, 'updated' => 0, 'unchanged' => 84, 'failed' => 11, 'blank_rows' => 2])
        ->assertJsonPath('new_categories', []);

    expect(ProductModel::query()->count())->toBe(84);
});
