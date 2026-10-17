<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Domain\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductResource extends JsonResource
{
    public function __construct(private readonly Product $product)
    {
        parent::__construct($product);
    }

    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->product->sku()->value(),
            'name' => $this->product->name()->value(),
            'description' => $this->product->description(),
            'category' => $this->product->category()->value(),
            'price' => $this->product->price()->toDecimalString(),
            'stock' => $this->product->stock()->quantity(),
            'weight_kg' => $this->product->weight()?->toKilogramString(),
        ];
    }
}
