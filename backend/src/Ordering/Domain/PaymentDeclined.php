<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class PaymentDeclined extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
