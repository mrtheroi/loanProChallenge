<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use DomainException;

final class ProductAlreadyExists extends DomainException
{
    public static function bySku(Sku $sku): self
    {
        return new self(sprintf('a product with sku %s already exists', $sku->value()));
    }
}
