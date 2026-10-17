<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Ecommerce\Catalog\Domain\Product;
use Ecommerce\Catalog\Domain\ProductAlreadyExists;
use Ecommerce\Catalog\Domain\ProductChangedConcurrently;
use Ecommerce\Catalog\Domain\ProductName;
use Ecommerce\Catalog\Domain\ProductNotFound;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Catalog\Domain\SkuIsReserved;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentProductRepository implements ProductRepository
{
    public function add(Product $product): Product
    {
        try {
            return DB::transaction(function () use ($product): Product {
                $model = ProductModel::query()->create(['sku' => $product->sku()->value(), ...$this->attributes($product)]);

                return $model->toProduct();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === 'products_sku_unique') {
                throw ProductModel::onlyTrashed()->where('sku', $product->sku()->value())->exists()
                    ? SkuIsReserved::bySku($product->sku())
                    : ProductAlreadyExists::bySku($product->sku());
            }

            throw $exception;
        }
    }

    public function save(Product $product): void
    {
        try {
            DB::transaction(function () use ($product): void {
                if (ProductModel::onlyTrashed()->where('sku', $product->sku()->value())->exists()) {
                    throw SkuIsReserved::bySku($product->sku());
                }

                ProductModel::query()->updateOrCreate(['sku' => $product->sku()->value()], $this->attributes($product));
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === 'products_sku_unique') {
                throw SkuIsReserved::bySku($product->sku());
            }

            throw $exception;
        }
    }

    public function update(Product $product, ?ProductName $expectedName = null): Product
    {
        return DB::transaction(function () use ($product, $expectedName): Product {
            $affected = ProductModel::query()
                ->where('sku', $product->sku()->value())
                ->when($expectedName !== null, fn ($query) => $query->where('name', $expectedName?->value()))
                ->update($this->attributes($product));

            if ($affected === 0) {
                throw $expectedName !== null && ProductModel::query()->where('sku', $product->sku()->value())->exists()
                    ? ProductChangedConcurrently::bySku($product->sku())
                    : ProductNotFound::bySku($product->sku()->value());
            }

            return ProductModel::query()->with('category')->where('sku', $product->sku()->value())->firstOrFail()->toProduct();
        });
    }

    public function findBySku(Sku $sku): ?Product
    {
        $model = ProductModel::query()->with('category')->where('sku', $sku->value())->first();

        if ($model === null) {
            return null;
        }

        return $model->toProduct();
    }

    public function delete(Sku $sku): void
    {
        if (ProductModel::query()->where('sku', $sku->value())->delete() === 0) {
            throw ProductNotFound::bySku($sku->value());
        }
    }

    /** @return array<string, int|string|null> */
    private function attributes(Product $product): array
    {
        $category = CategoryModel::query()->firstOrCreate(
            ['normalized_name' => $product->category()->normalized()],
            ['name' => $product->category()->value()],
        );

        return [
            'name' => $product->name()->value(),
            'description' => $product->description(),
            'category_id' => $category->id,
            'price_cents' => $product->price()->cents(),
            'stock' => $product->stock()->quantity(),
            'weight_grams' => $product->weight()?->grams(),
        ];
    }
}
