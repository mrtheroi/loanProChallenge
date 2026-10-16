<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Controllers;

use App\Models\User;
use Ecommerce\Identity\Http\Requests\LoginRequest;
use Ecommerce\Identity\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginController
{
    public function __invoke(LoginRequest $request): UserResource
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = $guard->user();

        return new UserResource($user);
    }
}
