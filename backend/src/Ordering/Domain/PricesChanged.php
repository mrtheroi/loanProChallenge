<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class PricesChanged extends DomainException
{
    /** @param  list<PriceChange>  $changes */
    private function __construct(public readonly array $changes)
    {
        parent::__construct('Some prices changed. Review your cart before paying.');
    }

    /** @param  list<PriceChange>  $changes */
    public static function for(array $changes): self
    {
        return new self($changes);
    }
}
