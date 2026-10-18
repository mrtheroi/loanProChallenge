<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

final readonly class CsvHeader
{
    public const array COLUMNS = ['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg'];

    /** @param list<string> $columns */
    private function __construct(private array $columns) {}

    /** @param list<string> $record */
    public static function fromRecord(array $record): self
    {
        $columns = array_map(fn (string $column): string => strtolower(trim($column)), $record);
        $unnamed = array_keys(array_filter($columns, fn (string $column): bool => $column === ''));
        if ($unnamed !== []) {
            throw InvalidCsvHeader::unnamed(array_map(fn (int $index): int => $index + 1, $unnamed));
        }

        $repeated = array_keys(array_filter(array_count_values($columns), fn (int $count): bool => $count > 1));
        if ($repeated !== []) {
            throw InvalidCsvHeader::repeated(array_map('strval', $repeated));
        }

        $unknown = array_values(array_diff($columns, self::COLUMNS));
        if ($unknown !== []) {
            throw InvalidCsvHeader::unknown($unknown);
        }

        $missing = array_values(array_diff(self::COLUMNS, $columns));
        if ($missing !== []) {
            throw InvalidCsvHeader::missing($missing);
        }

        return new self($columns);
    }

    /** @param list<string> $record */
    public function fieldCountError(array $record): ?string
    {
        return count($record) === count($this->columns)
            ? null
            : sprintf('has %d fields but the header has %d', count($record), count($this->columns));
    }

    /**
     * @param  list<string>  $record
     * @return array<string, string>
     */
    public function map(array $record): array
    {
        return array_combine($this->columns, $record);
    }
}
