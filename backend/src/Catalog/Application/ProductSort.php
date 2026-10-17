<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

enum ProductSort: string
{
    case NameAscending = 'name';
    case NameDescending = '-name';
    case PriceAscending = 'price';
    case PriceDescending = '-price';
}
