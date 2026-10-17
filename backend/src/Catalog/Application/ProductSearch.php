<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

interface ProductSearch
{
    public function search(ProductSearchCriteria $criteria): ProductPage;
}
