<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

use Closure;
use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Description;
use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\Stock;
use Ecommerce\Catalog\Domain\Weight;

final class ProductRowParser
{
    private const int MAX_REPORTED_SKU_LENGTH = 100;

    /** @param  array<string, string>  $fields */
    public static function isBlank(array $fields): bool
    {
        return array_filter($fields, fn (string $value): bool => trim($value) !== '') === [];
    }

    /** @param  array<string, string>  $fields */
    public function parse(array $fields): ParsedRow
    {
        $errors = [];
        $attempt = function (Closure $build) use (&$errors): mixed {
            try {
                return $build();
            } catch (InvalidProductAttribute $exception) {
                $errors[$exception->field()] = [$exception->reason()];

                return null;
            }
        };

        $sku = $attempt(fn (): Sku => Sku::fromString($fields['sku']));
        $name = $attempt(fn (): ProductName => ProductName::fromString($fields['name']));
        $description = $attempt(fn (): ?string => Description::from($fields['description']));
        $category = $attempt(fn (): CategoryName => CategoryName::fromString($fields['category']));
        $price = $attempt(fn (): Money => Money::fromDecimalString($fields['price']));
        $stock = $attempt(fn (): Stock => Stock::from($fields['stock']));
        $weight = $attempt(fn (): ?Weight => Weight::fromOptionalDecimalString($fields['weight_kg']));

        $reportedSku = $sku instanceof Sku ? $sku->value() : mb_substr(mb_scrub(trim($fields['sku']), 'UTF-8'), 0, self::MAX_REPORTED_SKU_LENGTH);

        if (! $sku instanceof Sku || ! $name instanceof ProductName || ! $category instanceof CategoryName
            || ! $price instanceof Money || ! $stock instanceof Stock || $errors !== []) {
            return new ParsedRow($reportedSku, null, self::ordered($errors));
        }

        return new ParsedRow(
            $reportedSku,
            Product::create($sku, $name, is_string($description) ? $description : null, $category, $price, $stock, $weight instanceof Weight ? $weight : null),
            [],
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @return array<string, list<string>>
     */
    private static function ordered(array $errors): array
    {
        return array_intersect_key(array_merge(array_fill_keys(CsvHeader::COLUMNS, []), $errors), $errors);
    }
}
