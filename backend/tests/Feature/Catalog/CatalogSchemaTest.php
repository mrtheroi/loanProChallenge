<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function insertRawProductRow(array $overrides): void
{
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Tools', 'normalized_name' => 'tools']);

    DB::table('products')->insert([
        'sku' => 'RAW-001',
        'name' => 'Raw product',
        'category_id' => $categoryId,
        'price_cents' => 100,
        'stock' => 1,
        'weight_grams' => null,
        ...$overrides,
    ]);
}

it('rejects negative numeric values even when the domain is bypassed', function (string $column): void {
    expect(fn () => insertRawProductRow([$column => -1]))->toThrow(QueryException::class, "products_{$column}_non_negative");
})->with(['price_cents', 'stock', 'weight_grams']);

it('accepts a valid raw row', function (): void {
    insertRawProductRow(['price_cents' => 0, 'stock' => 0, 'weight_grams' => 0]);

    expect(DB::table('products')->count())->toBe(1);
});

it('enables pg_trgm and indexes name, sku and description with trigram GIN indexes', function (): void {
    $extension = DB::table('pg_extension')->where('extname', 'pg_trgm')->exists();
    $definitions = DB::table('pg_indexes')->where('tablename', 'products')->pluck('indexdef');

    expect($extension)->toBeTrue();

    foreach (['name', 'sku', 'description'] as $column) {
        expect($definitions->contains(fn (string $definition): bool => str_contains($definition, "USING gin ({$column} gin_trgm_ops)")))
            ->toBeTrue("missing trigram index on {$column}");
    }
});

it('keeps the pg_trgm extension installed when its migration is rolled back', function (): void {
    $migration = require database_path('migrations/2026_10_16_100000_enable_pg_trgm_extension.php');

    $migration->down();

    expect(DB::table('pg_extension')->where('extname', 'pg_trgm')->exists())->toBeTrue();
});

it('does not soft delete categories', function (): void {
    expect(Schema::hasColumn('categories', 'deleted_at'))->toBeFalse();
});

it('rejects a blank or padded sku even when the domain is bypassed', function (string $sku): void {
    expect(fn () => insertRawProductRow(['sku' => $sku]))->toThrow(QueryException::class, 'products_sku_format');
})->with(['empty' => '', 'spaces only' => '   ', 'leading space' => ' RAW-002', 'trailing space' => 'RAW-002 ', 'leading tab' => "\tRAW-002"]);

it('rejects a blank or padded name even when the domain is bypassed', function (string $name): void {
    expect(fn () => insertRawProductRow(['name' => $name]))->toThrow(QueryException::class, 'products_name_trimmed_not_blank');
})->with(['empty' => '', 'spaces only' => '   ', 'leading space' => ' Raw product', 'trailing newline' => "Raw product\n"]);

it('rejects a sku outside the canonical format even when the domain is bypassed', function (string $sku): void {
    expect(fn () => insertRawProductRow(['sku' => $sku]))->toThrow(QueryException::class, 'products_sku_format');
})->with(['lowercase' => 'raw-002', 'slash' => 'AB/33', 'accented letter' => 'CAFÉ-1']);
