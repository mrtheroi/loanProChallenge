<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

interface CsvRowReader
{
    /** @return iterable<int, list<string>> records keyed by row number, the header being row 1 */
    public function read(string $path): iterable;
}
