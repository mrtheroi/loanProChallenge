<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;

final readonly class ProductSearchCriteria
{
    public function __construct(
        public ?string $text = null,
        public ?CategoryName $category = null,
        public ?Money $minPrice = null,
        public ?Money $maxPrice = null,
        public bool $inStockOnly = false,
        public ProductSort $sort = ProductSort::NameAscending,
        public int $page = 1,
        public int $perPage = 12,
    ) {}
}
