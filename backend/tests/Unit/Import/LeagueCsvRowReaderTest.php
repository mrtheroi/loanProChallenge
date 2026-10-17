<?php

declare(strict_types=1);

use Ecommerce\Import\Infrastructure\LeagueCsvRowReader;

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $contents);

    return $path;
}

/** @return array<int, list<string>> */
function readCsv(string $contents): array
{
    $path = csvFile($contents);

    try {
        return iterator_to_array((new LeagueCsvRowReader)->read($path));
    } finally {
        unlink($path);
    }
}

it('yields every record keyed by its row number with the header as row 1', function (): void {
    expect(readCsv("name,sku\nShoes,RS-001\nMug,TM-029\n"))->toBe([
        1 => ['name', 'sku'],
        2 => ['Shoes', 'RS-001'],
        3 => ['Mug', 'TM-029'],
    ]);
});

it('honours quoted commas, escaped quotes and quoted line breaks', function (): void {
    expect(readCsv("name,sku\n\"Comma, In Name\",CI-001\n\"Quote \"\"Inside\"\" Name\",QI-001\n\"Two\nlines\",TL-001\n"))->toBe([
        1 => ['name', 'sku'],
        2 => ['Comma, In Name', 'CI-001'],
        3 => ['Quote "Inside" Name', 'QI-001'],
        4 => ["Two\nlines", 'TL-001'],
    ]);
});

it('strips a UTF-8 byte order mark from the header', function (): void {
    expect(readCsv("\u{FEFF}name,sku\nShoes,RS-001\n")[1])->toBe(['name', 'sku']);
});

it('keeps rows made only of separators so they can be reported', function (): void {
    expect(readCsv("name,sku\n,\nShoes,RS-001\n"))->toBe([
        1 => ['name', 'sku'],
        2 => ['', ''],
        3 => ['Shoes', 'RS-001'],
    ]);
});

it('accepts windows line endings', function (): void {
    expect(readCsv("name,sku\r\nShoes,RS-001\r\n"))->toBe([1 => ['name', 'sku'], 2 => ['Shoes', 'RS-001']]);
});
