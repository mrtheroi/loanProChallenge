<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class InvalidOrderInput extends DomainException
{
    private function __construct(private readonly string $field, private readonly string $reason)
    {
        parent::__construct("{$field}: {$reason}");
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
