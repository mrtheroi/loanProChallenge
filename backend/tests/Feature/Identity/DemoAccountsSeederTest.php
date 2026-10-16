<?php

declare(strict_types=1);

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the demo accounts idempotently', function (): void {
    $this->artisan('db:seed')->assertSuccessful();
    $this->artisan('db:seed')->assertSuccessful();

    $roles = User::query()->orderBy('email')->get()->mapWithKeys(fn (User $user): array => [$user->email => $user->role]);

    expect($roles->all())->toBe([
        'admin@example.com' => Role::Admin,
        'buyer@example.com' => Role::Buyer,
    ]);

    User::query()->each(fn (User $user) => expect(Hash::check('password', $user->password))->toBeTrue());
});

it('keeps the role and password of an existing user that owns a demo email', function (): void {
    User::factory()->create(['email' => 'admin@example.com', 'password' => 'custom-secret']);

    $this->artisan('db:seed')->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.com')->sole();

    expect($user->role)->toBe(Role::Buyer)
        ->and(Hash::check('custom-secret', $user->password))->toBeTrue();
});

it('does not reset the changed password of an existing demo account', function (string $email): void {
    $this->artisan('db:seed')->assertSuccessful();
    User::query()->where('email', $email)->sole()->update(['password' => 'changed-secret']);

    $this->artisan('db:seed')->assertSuccessful();

    expect(Hash::check('changed-secret', User::query()->where('email', $email)->sole()->password))->toBeTrue();
})->with(['admin@example.com', 'buyer@example.com']);

it('creates no demo accounts in the production environment', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
