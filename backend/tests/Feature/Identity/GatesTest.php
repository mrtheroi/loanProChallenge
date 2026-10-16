<?php

declare(strict_types=1);

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function gatesTestUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

it('allows only admins to manage the catalog', function (Role $role, bool $allowed): void {
    expect(Gate::forUser(gatesTestUser($role))->allows('manage-catalog'))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'buyer' => [Role::Buyer, false],
]);

it('allows only buyers to purchase', function (Role $role, bool $allowed): void {
    expect(Gate::forUser(gatesTestUser($role))->allows('purchase'))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, false],
    'buyer' => [Role::Buyer, true],
]);

it('denies guests both abilities', function (string $ability): void {
    expect(Gate::allows($ability))->toBeFalse();
})->with(['manage-catalog', 'purchase']);
