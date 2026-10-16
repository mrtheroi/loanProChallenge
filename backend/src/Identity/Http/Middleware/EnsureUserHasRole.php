<?php

declare(strict_types=1);

namespace Ecommerce\Identity\Http\Middleware;

use App\Models\User;
use Closure;
use Ecommerce\Identity\Role;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        abort_unless($user->role === Role::from($role), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
