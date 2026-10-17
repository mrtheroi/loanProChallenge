<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\ProductNotFound;
use Ecommerce\Catalog\Domain\Sku;

final class ExistingSku
{
    public static function parse(string $sku): Sku
    {
        try {
            return Sku::fromString($sku);
        } catch (InvalidProductAttribute) {
            throw ProductNotFound::bySku($sku);
        }
    }
}
