<?php

declare(strict_types=1);

namespace Ecommerce\Identity;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');

            return [
                Limit::perMinute(5)->by(Str::lower(is_string($email) ? $email : '').'|'.$request->ip()),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        Gate::define('manage-catalog', fn (User $user): bool => $user->role === Role::Admin);
        Gate::define('purchase', fn (User $user): bool => $user->role === Role::Buyer);
    }
}
