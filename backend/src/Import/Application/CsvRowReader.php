<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

interface CsvRowReader
{
    /** @return iterable<int, list<string>> */
    public function read(string $path): iterable;
}
