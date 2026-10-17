<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

interface CategoryRepository
{
    public function exists(CategoryName $category): bool;
}
