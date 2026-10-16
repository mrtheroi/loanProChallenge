<?php

declare(strict_types=1);

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::middleware(['api', 'auth:sanctum', 'role:admin'])
        ->get('/api/testing/admin-only', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'role:admin'])
        ->get('/api/testing/role-without-auth', fn () => response()->json(['ok' => true]));
});

function roleMiddlewareUser(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

it('lets a user with the required role through', function (): void {
    $this->actingAs(roleMiddlewareUser(Role::Admin))
        ->getJson('/api/testing/admin-only')
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('forbids an authenticated user without the required role', function (): void {
    $this->actingAs(roleMiddlewareUser(Role::Buyer))
        ->getJson('/api/testing/admin-only')
        ->assertForbidden();
});

it('rejects a guest with 401', function (string $uri): void {
    $this->getJson($uri)->assertUnauthorized();
})->with(['/api/testing/admin-only', '/api/testing/role-without-auth']);
