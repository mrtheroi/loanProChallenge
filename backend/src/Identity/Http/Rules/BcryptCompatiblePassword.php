<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BcryptCompatiblePassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $fail("The {$attribute} field must be valid UTF-8 text.");

            return;
        }

        if (str_contains($value, "\0")) {
            $fail("The {$attribute} field must not contain null characters.");

            return;
        }

        if (strlen($value) > 72) {
            $fail("The {$attribute} field must not be greater than 72 bytes.");
        }
    }
}
