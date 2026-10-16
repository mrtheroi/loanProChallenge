<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Closure;
use Ecommerce\Catalog\Application\ProductData;
use Illuminate\Foundation\Http\FormRequest;

final class ProductRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'sku' => [$this->isMethod('PUT') ? 'sometimes' : 'required', 'string'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string'],
            'price' => ['required', 'string'],
            'stock' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && ! is_string($value)) {
                    $fail('The :attribute field must be an integer or an integer string.');
                }
            }],
            'weight_kg' => ['nullable', 'string'],
        ];
    }

    public function toProductData(string $pathSku = ''): ProductData
    {
        return new ProductData(
            $this->string('sku', $pathSku)->toString(),
            $this->string('name')->toString(),
            $this->string('description')->toString(),
            $this->string('category')->toString(),
            $this->string('price')->toString(),
            is_int($stock = $this->input('stock')) ? $stock : $this->string('stock')->toString(),
            $this->string('weight_kg')->toString(),
        );
    }
}
