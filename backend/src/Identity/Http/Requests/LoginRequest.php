<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Requests;

use Ecommerce\Identity\Http\Rules\BcryptCompatiblePassword;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    use NormalizesEmail;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', new BcryptCompatiblePassword],
        ];
    }
}
