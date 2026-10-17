<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class IdempotencyKeyReused extends DomainException
{
    public static function withDifferentRequest(): self
    {
        return new self('This Idempotency-Key was already used with a different request.');
    }
}
