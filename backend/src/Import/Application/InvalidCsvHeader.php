<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

use DomainException;

final class InvalidCsvHeader extends DomainException
{
    /** @param  list<string>  $columns */
    public static function missing(array $columns): self
    {
        return new self(sprintf('The CSV header is missing the column(s): %s.', self::list($columns)));
    }

    /** @param  list<string>  $columns */
    public static function repeated(array $columns): self
    {
        return new self(sprintf('The CSV header repeats the column(s): %s.', self::list($columns)));
    }

    /** @param  list<string>  $columns */
    public static function unknown(array $columns): self
    {
        return new self(sprintf('The CSV header has unknown column(s): %s.', self::list($columns)));
    }

    /** @param  list<string>  $columns */
    private static function list(array $columns): string
    {
        return mb_scrub(implode(', ', $columns), 'UTF-8');
    }
}
