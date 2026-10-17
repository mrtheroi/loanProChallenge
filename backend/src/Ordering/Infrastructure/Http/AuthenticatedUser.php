<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class AuthenticatedUser
{
    public static function id(Request $request): int
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user->id;
    }
}
