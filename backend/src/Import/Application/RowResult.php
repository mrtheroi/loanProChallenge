<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

final readonly class RowResult
{
    /** @param  array<string, list<string>>  $errors */
    public function __construct(
        public int $row,
        public string $sku,
        public RowStatus $status,
        public array $errors = [],
        public ?string $note = null,
    ) {}

    /** @param  array<string, list<string>>  $errors */
    public static function failed(int $row, string $sku, array $errors): self
    {
        return new self($row, $sku, RowStatus::Failed, $errors);
    }
}
