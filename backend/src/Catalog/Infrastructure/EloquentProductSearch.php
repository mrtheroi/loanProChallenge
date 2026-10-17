<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Ecommerce\Catalog\Application\ProductPage;
use Ecommerce\Catalog\Application\ProductSearch;
use Ecommerce\Catalog\Application\ProductSearchCriteria;
use Ecommerce\Catalog\Application\ProductSort;
use Illuminate\Database\Eloquent\Builder;

final class EloquentProductSearch implements ProductSearch
{
    public function search(ProductSearchCriteria $criteria): ProductPage
    {
        $query = ProductModel::query()->with('category');

        if ($criteria->text !== null) {
            $pattern = '%'.addcslashes($criteria->text, '\\%_').'%';
            $query->where(fn (Builder $where) => $where
                ->where('name', 'ilike', $pattern)
                ->orWhere('sku', 'ilike', $pattern)
                ->orWhere('description', 'ilike', $pattern));
        }

        if ($criteria->category !== null) {
            $query->whereHas('category', fn (Builder $category) => $category
                ->where('normalized_name', $criteria->category->normalized()));
        }

        if ($criteria->minPrice !== null) {
            $query->where('price_cents', '>=', $criteria->minPrice->cents());
        }

        if ($criteria->maxPrice !== null) {
            $query->where('price_cents', '<=', $criteria->maxPrice->cents());
        }

        if ($criteria->inStockOnly) {
            $query->where('stock', '>', 0);
        }

        $total = $query->count();

        $products = $query
            ->orderByRaw(match ($criteria->sort) {
                ProductSort::NameAscending => 'lower(name) asc',
                ProductSort::NameDescending => 'lower(name) desc',
                ProductSort::PriceAscending => 'price_cents asc',
                ProductSort::PriceDescending => 'price_cents desc',
            })
            ->orderBy('sku')
            ->forPage($criteria->page, $criteria->perPage)
            ->get()
            ->map(fn (ProductModel $model) => $model->toProduct())
            ->all();

        return new ProductPage(array_values($products), $total, $criteria->page, $criteria->perPage);
    }
}
