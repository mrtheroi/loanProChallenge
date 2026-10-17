<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Ecommerce\Ordering\Domain\StockItem;
use Ecommerce\Ordering\Domain\StockLedger;

final class EloquentStockLedger implements StockLedger
{
    public function lock(array $skus): array
    {
        $items = [];

        foreach (ProductModel::query()->whereIn('sku', $skus)->orderBy('sku')->lockForUpdate()->get() as $product) {
            $items[$product->sku] = new StockItem($product->id, $product->sku, $product->name, $product->price_cents, $product->stock);
        }

        return $items;
    }

    public function decrement(array $quantitiesByProductId): void
    {
        foreach ($quantitiesByProductId as $productId => $quantity) {
            ProductModel::query()->whereKey($productId)->decrement('stock', $quantity);
        }
    }
}
