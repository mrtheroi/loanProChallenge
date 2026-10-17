<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure;

use Ecommerce\Import\Application\CsvRowReader;
use Illuminate\Support\ServiceProvider;

final class ImportServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CsvRowReader::class => LeagueCsvRowReader::class,
    ];
}
