<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use DomainException;

final class ProductChangedConcurrently extends DomainException
{
    public static function bySku(Sku $sku): self
    {
        return new self(sprintf('Product "%s" was changed by another request.', $sku->value()));
    }
}
