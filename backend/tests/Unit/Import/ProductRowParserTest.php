<?php

declare(strict_types=1);

use Ecommerce\Import\Application\ProductRowParser;

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function csvRow(array $overrides = []): array
{
    return array_merge([
        'name' => 'Running Shoes',
        'sku' => 'rs-001',
        'description' => 'Lightweight',
        'category' => 'Footwear',
        'price' => '89.99',
        'stock' => '150',
        'weight_kg' => '0.35',
    ], $overrides);
}

it('parses a valid row into a product', function (): void {
    $parsed = (new ProductRowParser)->parse(csvRow());

    expect($parsed->errors)->toBe([])
        ->and($parsed->sku)->toBe('RS-001')
        ->and($parsed->product?->sku()->value())->toBe('RS-001')
        ->and($parsed->product?->price()->cents())->toBe(8999)
        ->and($parsed->product?->stock()->quantity())->toBe(150)
        ->and($parsed->product?->weight()?->grams())->toBe(350);
});

it('treats blank description and weight as absent', function (): void {
    $product = (new ProductRowParser)->parse(csvRow(['description' => ' ', 'weight_kg' => '']))->product;

    expect($product?->description())->toBeNull()
        ->and($product?->weight())->toBeNull();
});

it('reports every invalid field of a row without guessing', function (): void {
    $parsed = (new ProductRowParser)->parse(csvRow([
        'name' => '  ',
        'description' => "bad\0text",
        'category' => '',
        'price' => '$29.99',
        'stock' => '-5',
        'weight_kg' => 'heavy',
    ]));

    expect($parsed->product)->toBeNull()
        ->and($parsed->sku)->toBe('RS-001')
        ->and(array_keys($parsed->errors))->toBe(['name', 'description', 'category', 'price', 'stock', 'weight_kg']);
});

it('keeps the raw sku for the report when the sku itself is invalid', function (): void {
    $parsed = (new ProductRowParser)->parse(csvRow(['sku' => ' bad sku ']));

    expect($parsed->sku)->toBe('bad sku')
        ->and(array_keys($parsed->errors))->toBe(['sku']);
});

it('detects a row whose fields are all blank', function (): void {
    expect(ProductRowParser::isBlank(['name' => '', 'sku' => ' ']))->toBeTrue()
        ->and(ProductRowParser::isBlank(['name' => '', 'sku' => 'X']))->toBeFalse();
});

it('gives invalid rows that differ only in casing and spacing of name or category the same signature', function (): void {
    $parser = new ProductRowParser;
    $first = $parser->parse(csvRow(['price' => 'free']));
    $second = $parser->parse(csvRow(['price' => 'free', 'name' => ' running   SHOES ', 'category' => 'FOOTWEAR', 'sku' => 'RS-001']));
    $different = $parser->parse(csvRow(['price' => 'free', 'stock' => '9']));

    expect($second->signature)->toBe($first->signature)
        ->and($different->signature)->not->toBe($first->signature);
});
