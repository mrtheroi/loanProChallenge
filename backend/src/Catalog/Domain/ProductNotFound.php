<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use DomainException;

final class ProductNotFound extends DomainException
{
    public static function bySku(string $sku): self
    {
        return new self(sprintf('product %s was not found', $sku));
    }
}
