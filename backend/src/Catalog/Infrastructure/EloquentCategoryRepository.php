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

    public function namesInUse(): array
    {
        return array_values(CategoryModel::query()
            ->whereExists(fn ($products) => $products->selectRaw('1')->from('products')
                ->whereColumn('products.category_id', 'categories.id')
                ->whereNull('products.deleted_at'))
            ->orderBy('normalized_name')
            ->get(['name'])
            ->map(fn (CategoryModel $category): string => $category->name)
            ->all());
    }
}
