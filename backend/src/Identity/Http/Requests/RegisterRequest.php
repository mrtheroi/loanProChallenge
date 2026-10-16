<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    use NormalizesEmail;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value) === 1)) {
                        $fail("The {$attribute} field must be valid text without control characters.");
                    }
                },
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail("The {$attribute} field must not be greater than 72 bytes.");
                    }
                },
            ],
        ];
    }
}
