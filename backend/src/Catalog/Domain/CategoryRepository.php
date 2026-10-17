<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

interface CategoryRepository
{
    public function exists(CategoryName $category): bool;

    /** @return list<string> category names that have at least one visible product */
    public function namesInUse(): array;
}
