<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\CategoryRepository;

final class EloquentCategoryRepository implements CategoryRepository
{
    public function exists(CategoryName $category): bool
    {
        return CategoryModel::query()->where('normalized_name', $category->normalized())->exists();
    }
}
