<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class ImportProductsRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:10240'],
        ];
    }

    public function csvPath(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file->getRealPath() : '';
    }
}
