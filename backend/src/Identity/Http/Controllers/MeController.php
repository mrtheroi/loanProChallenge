<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Controllers;

use App\Models\User;
use Ecommerce\Identity\Http\Resources\UserResource;
use Illuminate\Container\Attributes\CurrentUser;

final class MeController
{
    public function __invoke(#[CurrentUser] User $user): UserResource
    {
        return new UserResource($user);
    }
}
