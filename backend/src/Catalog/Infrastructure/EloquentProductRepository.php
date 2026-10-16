<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\Stock;
use Ecommerce\Catalog\Domain\Weight;
use Illuminate\Support\Facades\DB;

final class EloquentProductRepository implements ProductRepository
{
    public function save(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $category = CategoryModel::query()->firstOrCreate(
                ['normalized_name' => $product->category()->normalized()],
                ['name' => $product->category()->value()],
            );

            ProductModel::query()->updateOrCreate(
                ['sku' => $product->sku()->value()],
                [
                    'name' => $product->name()->value(),
                    'description' => $product->description(),
                    'category_id' => $category->id,
                    'price_cents' => $product->price()->cents(),
                    'stock' => $product->stock()->quantity(),
                    'weight_grams' => $product->weight()?->grams(),
                ],
            );
        });
    }

    public function findBySku(Sku $sku): ?Product
    {
        $model = ProductModel::query()->with('category')->where('sku', $sku->value())->first();

        if ($model === null) {
            return null;
        }

        return Product::create(
            Sku::fromString($model->sku),
            ProductName::fromString($model->name),
            $model->description,
            CategoryName::fromString($model->category->name),
            Money::fromCents($model->price_cents),
            Stock::from($model->stock),
            $model->weight_grams === null ? null : Weight::fromGrams($model->weight_grams),
        );
    }
}
