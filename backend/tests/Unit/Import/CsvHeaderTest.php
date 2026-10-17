<?php

declare(strict_types=1);

use Ecommerce\Import\Application\CsvHeader;
use Ecommerce\Import\Application\InvalidCsvHeader;

it('maps a record to the expected columns in any order, ignoring case and spaces', function (): void {
    $header = CsvHeader::fromRecord([' SKU ', 'name', 'Description', 'category', 'price', 'stock', 'weight_kg']);

    expect($header->map(['RS-001', 'Shoes', 'Light', 'Footwear', '9.99', '3', '0.35']))->toBe([
        'sku' => 'RS-001',
        'name' => 'Shoes',
        'description' => 'Light',
        'category' => 'Footwear',
        'price' => '9.99',
        'stock' => '3',
        'weight_kg' => '0.35',
    ]);
});

it('rejects a header with a missing, duplicate or unknown column', function (array $record, string $message): void {
    expect(fn () => CsvHeader::fromRecord($record))->toThrow(InvalidCsvHeader::class, $message);
})->with([
    'missing' => [['name', 'sku', 'description', 'category', 'price', 'stock'], 'The CSV header is missing the column(s): weight_kg.'],
    'duplicate' => [['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg', 'Name'], 'The CSV header repeats the column(s): name.'],
    'unknown' => [['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg', 'color'], 'The CSV header has unknown column(s): color.'],
    'empty' => [[''], 'The CSV header is missing the column(s): name, sku, description, category, price, stock, weight_kg.'],
]);

it('reports the field count of a record that does not match the header', function (): void {
    $header = CsvHeader::fromRecord(['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg']);

    expect($header->fieldCountError(['a', 'b']))->toBe('has 2 fields but the header has 7')
        ->and($header->fieldCountError(['a', 'b', 'c', 'd', 'e', 'f', 'g']))->toBeNull();
});
