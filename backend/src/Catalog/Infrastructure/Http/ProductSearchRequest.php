<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure\Http;

use Ecommerce\Catalog\Application\ProductSearchCriteria;
use Ecommerce\Catalog\Application\ProductSort;
use Ecommerce\Catalog\Domain\CategoryName;
use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductSearchRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 12;

    private const int MAX_PER_PAGE = 50;

    private const int MAX_PAGE = 1_000_000;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'regex:/^\P{Cc}*$/Du', 'max:100'],
            'category' => ['nullable', 'string'],
            'min_price' => ['nullable', 'string'],
            'max_price' => ['nullable', 'string'],
            'in_stock' => ['nullable', 'in:1,0,true,false'],
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    public function toCriteria(): ProductSearchCriteria
    {
        $text = trim($this->string('q')->toString());

        $category = $this->string('category')->toString();
        $minPrice = $this->price('min_price');
        $maxPrice = $this->price('max_price');

        if ($minPrice !== null && $maxPrice !== null && $minPrice->cents() > $maxPrice->cents()) {
            throw InvalidProductAttribute::because('min_price', 'must not be greater than max_price');
        }

        return new ProductSearchCriteria(
            text: $text === '' ? null : $text,
            category: trim($category) === '' ? null : CategoryName::fromString($category),
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            inStockOnly: $this->boolean('in_stock'),
            sort: $this->enum('sort', ProductSort::class) ?? ProductSort::NameAscending,
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', self::DEFAULT_PER_PAGE),
        );
    }

    private function price(string $field): ?Money
    {
        $value = trim($this->string($field)->toString());

        if ($value === '') {
            return null;
        }

        try {
            return Money::fromDecimalString($value);
        } catch (InvalidProductAttribute $exception) {
            throw InvalidProductAttribute::because($field, $exception->reason());
        }
    }
}
