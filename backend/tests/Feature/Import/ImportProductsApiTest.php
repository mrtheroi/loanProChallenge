<?php

declare(strict_types=1);

use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductAlreadyExists;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Catalog\AdminCatalog;

uses(RefreshDatabase::class);

const IMPORT_HEADER = "name,sku,description,category,price,stock,weight_kg\n";

/** @return TestResponse<JsonResponse> */
function importCsv(string $contents, string $filename = 'products.csv'): TestResponse
{
    return test()->actingAs(AdminCatalog::admin())->post(
        '/api/admin/products/import',
        ['file' => UploadedFile::fake()->createWithContent($filename, $contents)],
        ['Accept' => 'application/json'],
    );
}

it('creates new products and reports each row and the new categories', function (): void {
    importCsv(IMPORT_HEADER."Running Shoes,rs-001,Light,Footwear,89.99,150,0.35\nCoffee,CB-010,\"Single origin, 1kg\",Food & Beverage,18.75,500,\n")
        ->assertOk()
        ->assertExactJson([
            'summary' => ['created' => 2, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'blank_rows' => 0],
            'new_categories' => ['Footwear', 'Food & Beverage'],
            'rows' => [
                ['row' => 2, 'sku' => 'RS-001', 'status' => 'created', 'errors' => [], 'note' => null],
                ['row' => 3, 'sku' => 'CB-010', 'status' => 'created', 'errors' => [], 'note' => null],
            ],
        ]);

    $coffee = ProductModel::query()->with('category')->where('sku', 'CB-010')->sole();
    expect($coffee->description)->toBe('Single origin, 1kg')
        ->and($coffee->category->name)->toBe('Food & Beverage')
        ->and($coffee->price_cents)->toBe(1875)
        ->and($coffee->stock)->toBe(500)
        ->and($coffee->weight_grams)->toBeNull();
});

it('reports every invalid field of a failed row and writes nothing for it', function (): void {
    importCsv(IMPORT_HEADER.",HD-099,Headphones,Electronics,\$29.99,-5,0.25\n")
        ->assertOk()
        ->assertExactJson([
            'summary' => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 1, 'blank_rows' => 0],
            'new_categories' => [],
            'rows' => [[
                'row' => 2,
                'sku' => 'HD-099',
                'status' => 'failed',
                'errors' => [
                    'name' => ['is required'],
                    'price' => ['must be a non-negative decimal number with at most 2 decimals'],
                    'stock' => ['must be a non-negative integer'],
                ],
                'note' => null,
            ]],
        ]);

    expect(ProductModel::withTrashed()->count())->toBe(0);
});

function seedExistingShoes(): void
{
    test()->actingAs(AdminCatalog::admin())->postJson('/api/admin/products', [
        'name' => 'Running Shoes', 'sku' => 'RS-001', 'description' => 'Light', 'category' => 'Footwear',
        'price' => '89.99', 'stock' => 150, 'weight_kg' => '0.350',
    ])->assertCreated();
}

it('updates an existing sku whose normalized name matches, keeping the stored name', function (): void {
    seedExistingShoes();

    importCsv(IMPORT_HEADER."  running   SHOES ,rs-001,Better arch,footwear,94.99,120,0.35\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 0, 'updated' => 1, 'unchanged' => 0, 'failed' => 0, 'blank_rows' => 0])
        ->assertJsonPath('rows.0', ['row' => 2, 'sku' => 'RS-001', 'status' => 'updated', 'errors' => [], 'note' => null])
        ->assertJsonPath('new_categories', []);

    $shoes = ProductModel::query()->where('sku', 'RS-001')->sole();
    expect($shoes->name)->toBe('Running Shoes')
        ->and($shoes->description)->toBe('Better arch')
        ->and($shoes->price_cents)->toBe(9499)
        ->and($shoes->stock)->toBe(120);
});

it('reports an existing sku with identical data as unchanged', function (): void {
    seedExistingShoes();
    $before = ProductModel::query()->where('sku', 'RS-001')->sole()->updated_at;

    importCsv(IMPORT_HEADER."Running Shoes,RS-001,Light,FOOTWEAR,89.99,150,0.350\n")
        ->assertOk()
        ->assertJsonPath('rows.0', ['row' => 2, 'sku' => 'RS-001', 'status' => 'unchanged', 'errors' => [], 'note' => null]);

    expect(ProductModel::query()->where('sku', 'RS-001')->sole()->updated_at?->equalTo($before))->toBeTrue();
});

it('fails a row whose sku exists under a different name because the import cannot rename', function (): void {
    seedExistingShoes();

    importCsv(IMPORT_HEADER."Trail Shoes,RS-001,Light,Footwear,89.99,150,0.35\n")
        ->assertOk()
        ->assertJsonPath('rows.0', ['row' => 2, 'sku' => 'RS-001', 'status' => 'failed', 'errors' => [
            'name' => ['does not match the existing product "Running Shoes"; the import cannot rename products'],
        ], 'note' => null]);

    expect(ProductModel::query()->where('sku', 'RS-001')->sole()->name)->toBe('Running Shoes');
});

it('fails a row whose sku belongs to a deleted product', function (): void {
    seedExistingShoes();
    test()->actingAs(AdminCatalog::admin())->deleteJson('/api/admin/products/RS-001')->assertNoContent();

    importCsv(IMPORT_HEADER."Running Shoes,RS-001,Light,Footwear,89.99,150,0.35\n")
        ->assertOk()
        ->assertJsonPath('rows.0', ['row' => 2, 'sku' => 'RS-001', 'status' => 'failed', 'errors' => [
            'sku' => ['belongs to a deleted product and cannot be reused'],
        ], 'note' => null]);

    expect(ProductModel::query()->where('sku', 'RS-001')->exists())->toBeFalse();
});

it('rejects every row of a sku repeated in the file with different data', function (): void {
    importCsv(IMPORT_HEADER
        ."Bluetooth Speaker,BS-021,Portable,Electronics,59.99,110,0.8\n"
        ."Running Shoes,RS-001,Light,Footwear,89.99,150,0.35\n"
        ."Bluetooth Speaker,bs-021,Other text,Electronics,49.99,200,0.75\n"
        ."Bluetooth Speaker,BS-021,Portable,Electronics,59.99,110,0.8\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'failed' => 3, 'blank_rows' => 0])
        ->assertJsonPath('rows', [
            ['row' => 2, 'sku' => 'BS-021', 'status' => 'failed', 'errors' => ['sku' => ['appears in rows 2, 4, 5 with different data']], 'note' => null],
            ['row' => 3, 'sku' => 'RS-001', 'status' => 'created', 'errors' => [], 'note' => null],
            ['row' => 4, 'sku' => 'BS-021', 'status' => 'failed', 'errors' => ['sku' => ['appears in rows 2, 4, 5 with different data']], 'note' => null],
            ['row' => 5, 'sku' => 'BS-021', 'status' => 'failed', 'errors' => ['sku' => ['appears in rows 2, 4, 5 with different data']], 'note' => null],
        ]);

    expect(ProductModel::query()->pluck('sku')->all())->toBe(['RS-001']);
});

it('rejects every row of a repeated sku when one of its rows is invalid', function (): void {
    importCsv(IMPORT_HEADER."Lamp,DL-007,Desk,Home,45.50,3,2.1\nLamp,DL-007,Desk,Home,45.50,-5,2.1\n")
        ->assertOk()
        ->assertJsonPath('rows.0.errors', ['sku' => ['appears in rows 2, 3 with different data']])
        ->assertJsonPath('rows.1.errors', ['sku' => ['appears in rows 2, 3 with different data'], 'stock' => ['must be a non-negative integer']]);

    expect(ProductModel::query()->count())->toBe(0);
});

it('applies identical repeated rows once and reports the repeats as unchanged', function (): void {
    importCsv(IMPORT_HEADER."Mug,TM-029,Ceramic,Kitchen,16.99,320,0.3\nMug,tm-029,Ceramic,kitchen,16.99,320,0.300\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 1, 'updated' => 0, 'unchanged' => 1, 'failed' => 0, 'blank_rows' => 0])
        ->assertJsonPath('rows.1', ['row' => 3, 'sku' => 'TM-029', 'status' => 'unchanged', 'errors' => [], 'note' => 'identical to row 2']);
});

it('skips and counts rows whose fields are all blank', function (): void {
    importCsv(IMPORT_HEADER.",,,,,,\n  , ,,,,,\n\n,,\nMug,TM-029,,Kitchen,16.99,3,\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 1, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'blank_rows' => 4])
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.row', 6);
});

it('fails a row whose field count differs from the header', function (): void {
    importCsv(IMPORT_HEADER."Mug,TM-029,Ceramic,Kitchen,16.99,3\nCup,CU-001,Ceramic,Kitchen,6.99,3,0.2,extra\n")
        ->assertOk()
        ->assertJsonPath('rows', [
            ['row' => 2, 'sku' => '', 'status' => 'failed', 'errors' => ['row' => ['has 6 fields but the header has 7']], 'note' => null],
            ['row' => 3, 'sku' => '', 'status' => 'failed', 'errors' => ['row' => ['has 8 fields but the header has 7']], 'note' => null],
        ]);

    expect(ProductModel::query()->count())->toBe(0);
});

it('rejects the whole file when the header is not the expected one', function (string $contents, string $message): void {
    importCsv($contents)
        ->assertUnprocessable()
        ->assertExactJson(['message' => $message, 'errors' => ['file' => [$message]]]);

    expect(ProductModel::query()->count())->toBe(0);
})->with([
    'missing column' => ["name,sku,description,category,price,stock\nMug,TM-029,,Kitchen,1,1\n", 'The CSV header is missing the column(s): weight_kg.'],
    'empty file' => ['', 'The CSV header is missing the column(s): name, sku, description, category, price, stock, weight_kg.'],
    'trailing comma' => ["name,sku,description,category,price,stock,weight_kg,\nMug,TM-029,,Kitchen,1,1,,\n", 'The CSV header has unnamed column(s) at position(s): 8.'],
    'invalid utf-8 column' => ["name,sku,description,category,price,stock,weight_kg,caf\xE9\n", 'The CSV header has unknown column(s): caf?.'],
]);

it('rejects a file with more than 5000 data rows before writing anything', function (): void {
    $rows = '';
    foreach (range(1, 5001) as $index) {
        $rows .= "Item {$index},IT-{$index},,Bulk,1.00,1,\n";
    }

    importCsv(IMPORT_HEADER.$rows)
        ->assertUnprocessable()
        ->assertJsonPath('errors.file', ['The CSV file has more than 5000 data rows; split it into smaller files.']);

    expect(ProductModel::query()->count())->toBe(0);
});

it('rejects a missing upload or a file that is not a csv', function (array $payload): void {
    test()->actingAs(AdminCatalog::admin())
        ->post('/api/admin/products/import', $payload, ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->with([
    'missing' => fn (): array => [],
    'not a file' => fn (): array => ['file' => 'name,sku'],
    'wrong extension' => fn (): array => ['file' => UploadedFile::fake()->createWithContent('products.pdf', IMPORT_HEADER)],
    'larger than 10 MB' => fn (): array => ['file' => UploadedFile::fake()->create('products.csv', 10241, 'text/csv')],
]);

it('reports a raw invalid sku as valid UTF-8 text', function (): void {
    importCsv(IMPORT_HEADER."Mug,TM\xE9029,,Kitchen,1.00,1,\n")
        ->assertOk()
        ->assertJsonPath('rows.0.sku', 'TM?029')
        ->assertJsonPath('rows.0.errors', ['sku' => ['must be valid UTF-8 text without control characters']]);
});

it('only lets admins import', function (): void {
    $file = UploadedFile::fake()->createWithContent('products.csv', IMPORT_HEADER."Mug,TM-029,,Kitchen,1.00,1,\n");

    $this->post('/api/admin/products/import', ['file' => $file], ['Accept' => 'application/json'])->assertUnauthorized();
    $this->actingAs(AdminCatalog::buyer())
        ->post('/api/admin/products/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect(ProductModel::query()->count())->toBe(0);
});

it('reports rows that lose a race with another writer and keeps importing the rest', function (): void {
    seedExistingShoes();
    $real = app(ProductRepository::class);
    app()->instance(ProductRepository::class, new class($real) implements ProductRepository
    {
        public function __construct(private ProductRepository $real) {}

        public function findBySku(Sku $sku): ?Product
        {
            if ($sku->value() === 'RS-001') {
                $found = $this->real->findBySku($sku);
                $this->real->delete($sku);

                return $found;
            }

            return $sku->value() === 'TAKEN-1' ? null : $this->real->findBySku($sku);
        }

        public function add(Product $product): Product
        {
            if ($product->sku()->value() === 'TAKEN-1') {
                throw ProductAlreadyExists::bySku($product->sku());
            }

            return $this->real->add($product);
        }

        public function update(Product $product): Product
        {
            return $this->real->update($product);
        }

        public function save(Product $product): void
        {
            $this->real->save($product);
        }

        public function delete(Sku $sku): void
        {
            $this->real->delete($sku);
        }
    });

    importCsv(IMPORT_HEADER
        ."Running Shoes,RS-001,Changed,Footwear,1.00,1,\n"
        ."Taken,TAKEN-1,,Misc,1.00,1,\n"
        ."Mug,TM-029,,Kitchen,1.00,1,\n")
        ->assertOk()
        ->assertJsonPath('rows', [
            ['row' => 2, 'sku' => 'RS-001', 'status' => 'failed', 'errors' => ['sku' => ['was deleted by another request during the import']], 'note' => null],
            ['row' => 3, 'sku' => 'TAKEN-1', 'status' => 'failed', 'errors' => ['sku' => ['was created by another request during the import']], 'note' => null],
            ['row' => 4, 'sku' => 'TM-029', 'status' => 'created', 'errors' => [], 'note' => null],
        ]);
});

it('reports an identical repeat as failed when the first of those rows failed', function (): void {
    seedExistingShoes();

    importCsv(IMPORT_HEADER."Trail Shoes,RS-001,Light,Footwear,89.99,150,0.35\nTrail Shoes,RS-001,Light,Footwear,89.99,150,0.35\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 2, 'blank_rows' => 0])
        ->assertJsonPath('rows.1', ['row' => 3, 'sku' => 'RS-001', 'status' => 'failed', 'errors' => [
            'name' => ['does not match the existing product "Running Shoes"; the import cannot rename products'],
        ], 'note' => 'identical to row 2']);
});

it('treats identical invalid rows as repeats instead of different data', function (): void {
    importCsv(IMPORT_HEADER."Mat,YM-015,Yoga,Sports,free,200,1.2\nMat,ym-015,Yoga,Sports,free,200,1.2\n")
        ->assertOk()
        ->assertJsonPath('summary', ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 2, 'blank_rows' => 0])
        ->assertJsonPath('rows', [
            ['row' => 2, 'sku' => 'YM-015', 'status' => 'failed', 'errors' => ['price' => ['must be a non-negative decimal number with at most 2 decimals']], 'note' => null],
            ['row' => 3, 'sku' => 'YM-015', 'status' => 'failed', 'errors' => ['price' => ['must be a non-negative decimal number with at most 2 decimals']], 'note' => 'identical to row 2'],
        ]);
});

it('stores sql and script payloads as plain text without executing them', function (): void {
    seedExistingShoes();
    $sql = "Robert'); DROP TABLE products;--";
    $delete = "x'; DELETE FROM products; DROP TABLE users; --";
    $script = "<script>alert('xss')</script>";

    importCsv(IMPORT_HEADER."\"{$sql}\",SQL-001,\"{$delete}\",\"{$script}\",9.99,50,0.5\n")
        ->assertOk()
        ->assertJsonPath('rows.0.status', 'created');

    $stored = ProductModel::query()->with('category')->where('sku', 'SQL-001')->sole();
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(ProductModel::query()->where('sku', 'RS-001')->exists())->toBeTrue()
        ->and($stored->name)->toBe($sql)
        ->and($stored->description)->toBe($delete)
        ->and($stored->category->name)->toBe($script);
});
