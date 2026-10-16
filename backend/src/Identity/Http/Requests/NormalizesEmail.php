<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Requests;

use Illuminate\Support\Str;

trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
