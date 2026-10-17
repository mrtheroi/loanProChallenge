<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

final readonly class RowResult
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public int $row,
        public string $sku,
        public RowStatus $status,
        public array $errors = [],
        public ?string $note = null,
    ) {}

    /** @param array<string, list<string>> $errors */
    public static function failed(int $row, string $sku, array $errors): self
    {
        return new self($row, $sku, RowStatus::Failed, $errors);
    }

    public function repeatedAt(int $row): self
    {
        $note = sprintf('identical to row %d', $this->row);

        return $this->status === RowStatus::Failed
            ? new self($row, $this->sku, RowStatus::Failed, $this->errors, $note)
            : new self($row, $this->sku, RowStatus::Unchanged, note: $note);
    }
}
