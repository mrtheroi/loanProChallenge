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
