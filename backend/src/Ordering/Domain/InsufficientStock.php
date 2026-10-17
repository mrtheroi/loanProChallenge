<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DomainException;

final class InsufficientStock extends DomainException
{
    /** @param  list<Unavailable>  $items */
    private function __construct(public readonly array $items)
    {
        parent::__construct('Some items are not available in the requested quantity.');
    }

    /** @param  list<Unavailable>  $items */
    public static function for(array $items): self
    {
        return new self($items);
    }
}
