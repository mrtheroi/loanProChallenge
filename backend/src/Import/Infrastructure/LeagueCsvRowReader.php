<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure;

use Ecommerce\Import\Application\CsvRowReader;
use League\Csv\Reader;

final class LeagueCsvRowReader implements CsvRowReader
{
    public function read(string $path): iterable
    {
        $reader = Reader::from($path);
        $reader->setEscape('');
        $reader->includeEmptyRecords();

        foreach ($reader->getRecords() as $offset => $record) {
            $values = array_map(fn (?string $value): string => $value ?? '', array_values($record));

            yield $offset + 1 => $values === [''] ? [] : $values;
        }
    }
}
