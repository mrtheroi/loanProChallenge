<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Controllers;

use App\Models\User;
use Ecommerce\Identity\Http\Requests\RegisterRequest;
use Ecommerce\Identity\Http\Resources\UserResource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class RegisterController
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        }

        Auth::guard('web')->login($user);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
