<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use DomainException;

final class SkuIsReserved extends DomainException
{
    private function __construct(private readonly Sku $sku)
    {
        parent::__construct(sprintf('sku %s is reserved and cannot be reused', $sku->value()));
    }

    public static function bySku(Sku $sku): self
    {
        return new self($sku);
    }

    public function sku(): Sku
    {
        return $this->sku;
    }
}
