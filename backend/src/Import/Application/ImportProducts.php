<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

use Ecommerce\Catalog\Domain\CategoryRepository;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductAlreadyExists;
use Ecommerce\Catalog\Domain\ProductNotFound;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\SkuIsReserved;

final readonly class ImportProducts
{
    public const int MAX_ROWS = 5000;

    public function __construct(
        private CsvRowReader $reader,
        private ProductRowParser $parser,
        private ProductRepository $products,
        private CategoryRepository $categories,
    ) {}

    public function handle(string $path): ImportReport
    {
        $header = null;
        $parsedRows = [];
        $fieldCountErrors = [];
        $blankRows = 0;
        $dataRows = 0;

        foreach ($this->reader->read($path) as $row => $record) {
            if ($header === null) {
                $header = CsvHeader::fromRecord($record);

                continue;
            }

            if (++$dataRows > self::MAX_ROWS) {
                throw CsvTooLarge::rows(self::MAX_ROWS);
            }

            $fieldCountError = $header->fieldCountError($record);
            if ($fieldCountError !== null) {
                $fieldCountErrors[$row] = $fieldCountError;

                continue;
            }

            $fields = $header->map($record);
            if (ProductRowParser::isBlank($fields)) {
                $blankRows++;

                continue;
            }

            $parsedRows[$row] = $this->parser->parse($fields);
        }

        if ($header === null) {
            throw InvalidCsvHeader::missing(CsvHeader::COLUMNS);
        }

        $duplicates = self::duplicateErrors($parsedRows);
        $results = [];
        $newCategories = [];
        $applied = [];

        foreach ($fieldCountErrors as $row => $error) {
            $results[$row] = RowResult::failed($row, '', ['row' => [$error]]);
        }

        foreach ($parsedRows as $row => $parsed) {
            if (isset($duplicates[$row])) {
                $results[$row] = RowResult::failed($row, $parsed->sku, array_merge(['sku' => [$duplicates[$row]]], $parsed->errors));
            } elseif ($parsed->product === null) {
                $results[$row] = RowResult::failed($row, $parsed->sku, $parsed->errors);
            } elseif (isset($applied[$parsed->sku])) {
                $results[$row] = $applied[$parsed->sku]->repeatedAt($row);
            } else {
                $results[$row] = $applied[$parsed->sku] = $this->apply($row, $parsed->product, $newCategories);
            }
        }

        ksort($results);

        return new ImportReport(array_values($results), array_values($newCategories), $blankRows);
    }

    /**
     * @param  array<int, ParsedRow>  $parsedRows
     * @return array<int, string>
     */
    private static function duplicateErrors(array $parsedRows): array
    {
        $groups = [];
        foreach ($parsedRows as $row => $parsed) {
            if (! array_key_exists('sku', $parsed->errors)) {
                $groups[$parsed->sku][$row] = $parsed->product === null ? "invalid:{$row}" : self::signature($parsed->product);
            }
        }

        $errors = [];
        foreach ($groups as $signatures) {
            if (count(array_unique($signatures)) > 1) {
                $message = sprintf('appears in rows %s with different data', implode(', ', array_keys($signatures)));
                $errors += array_fill_keys(array_keys($signatures), $message);
            }
        }

        return $errors;
    }

    private static function signature(Product $product): string
    {
        return serialize([
            $product->name()->normalized(),
            $product->description(),
            $product->category()->normalized(),
            $product->price()->cents(),
            $product->stock()->quantity(),
            $product->weight()?->grams(),
        ]);
    }

    /** @param  array<string, string>  $newCategories */
    private function apply(int $row, Product $product, array &$newCategories): RowResult
    {
        $sku = $product->sku()->value();
        $existing = $this->products->findBySku($product->sku());

        if ($existing !== null && $existing->name()->normalized() !== $product->name()->normalized()) {
            return RowResult::failed($row, $sku, ['name' => [sprintf(
                'does not match the existing product "%s"; the import cannot rename products',
                $existing->name()->value(),
            )]]);
        }

        if ($existing !== null && self::sameData($existing, $product)) {
            return new RowResult($row, $sku, RowStatus::Unchanged);
        }

        $category = $product->category();
        $isNewCategory = ! $this->categories->exists($category);

        try {
            $saved = $existing === null
                ? $this->products->add($product)
                : $this->products->update(self::keepingName($existing, $product));
        } catch (SkuIsReserved) {
            return RowResult::failed($row, $sku, ['sku' => ['belongs to a deleted product and cannot be reused']]);
        } catch (ProductAlreadyExists) {
            return RowResult::failed($row, $sku, ['sku' => ['was created by another request during the import']]);
        } catch (ProductNotFound) {
            return RowResult::failed($row, $sku, ['sku' => ['was deleted by another request during the import']]);
        }

        if ($isNewCategory) {
            $newCategories[$category->normalized()] = $saved->category()->value();
        }

        return new RowResult($row, $sku, $existing === null ? RowStatus::Created : RowStatus::Updated);
    }

    private static function keepingName(Product $existing, Product $incoming): Product
    {
        return Product::create(
            $existing->sku(),
            $existing->name(),
            $incoming->description(),
            $incoming->category(),
            $incoming->price(),
            $incoming->stock(),
            $incoming->weight(),
        );
    }

    private static function sameData(Product $a, Product $b): bool
    {
        return $a->description() === $b->description()
            && $a->category()->normalized() === $b->category()->normalized()
            && $a->price()->cents() === $b->price()->cents()
            && $a->stock()->quantity() === $b->stock()->quantity()
            && $a->weight()?->grams() === $b->weight()?->grams();
    }
}
