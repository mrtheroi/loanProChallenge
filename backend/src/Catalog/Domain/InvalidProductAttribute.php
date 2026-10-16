<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use DomainException;

final class InvalidProductAttribute extends DomainException
{
    private function __construct(private readonly string $field, private readonly string $reason)
    {
        parent::__construct(sprintf('%s: %s', $field, $reason));
    }

    public static function because(string $field, string $reason): self
    {
        return new self($field, $reason);
    }

    public function field(): string
    {
        return $this->field;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
