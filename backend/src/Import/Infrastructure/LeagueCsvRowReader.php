<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure;

use Ecommerce\Import\Application\CsvRowReader;
use League\Csv\Reader;

final class LeagueCsvRowReader implements CsvRowReader
{
    public function read(string $path): iterable
    {
        foreach (Reader::from($path)->getRecords() as $offset => $record) {
            yield $offset + 1 => array_map(fn (?string $value): string => $value ?? '', array_values($record));
        }
    }
}
