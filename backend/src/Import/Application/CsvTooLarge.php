<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

use DomainException;

final class CsvTooLarge extends DomainException
{
    public static function rows(int $maxRows): self
    {
        return new self(sprintf('The CSV file has more than %d data rows; split it into smaller files.', $maxRows));
    }
}
