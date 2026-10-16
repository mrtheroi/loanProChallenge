<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Controllers;

use App\Models\User;
use Ecommerce\Identity\Http\Requests\LoginRequest;
use Ecommerce\Identity\Http\Resources\UserResource;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginController
{
    public function __invoke(LoginRequest $request): UserResource
    {
        if (! $request->hasSession()) {
            throw new HttpResponseException(response()->json(['message' => 'Authentication requires the first-party SPA origin.'], 403));
        }

        $guard = Auth::guard('web');

        if (! $guard->attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = $guard->user();

        return new UserResource($user);
    }
}
