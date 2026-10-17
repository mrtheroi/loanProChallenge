<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

final readonly class ImportReport
{
    /**
     * @param  list<RowResult>  $rows
     * @param  list<string>  $newCategories
     */
    public function __construct(
        public array $rows,
        public array $newCategories,
        public int $blankRows,
    ) {}

    public function count(RowStatus $status): int
    {
        return count(array_filter($this->rows, fn (RowResult $row): bool => $row->status === $status));
    }
}
