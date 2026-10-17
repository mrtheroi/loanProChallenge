<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

final class ListOrdersRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 10;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
