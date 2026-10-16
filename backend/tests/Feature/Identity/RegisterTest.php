<?php

declare(strict_types=1);

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withHeader('Referer', 'http://localhost:8000');
});

function registerPayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        ...$overrides,
    ];
}

it('registers a buyer and returns the user shape', function (): void {
    $response = $this->postJson('/api/auth/register', registerPayload());

    $response->assertCreated()
        ->assertExactJson(['data' => [
            'id' => User::query()->sole()->id,
            'name' => 'Ada Buyer',
            'email' => 'ada@example.com',
            'role' => 'buyer',
        ]]);

    expect(User::query()->sole()->role)->toBe(Role::Buyer);
});

it('ignores a role sent in the registration payload', function (): void {
    $this->postJson('/api/auth/register', registerPayload(['role' => 'admin']))
        ->assertCreated()
        ->assertJsonPath('data.role', 'buyer');

    expect(User::query()->sole()->role)->toBe(Role::Buyer);
});

it('rejects invalid registration input', function (array $overrides, string $field): void {
    $this->postJson('/api/auth/register', registerPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'unconfirmed password' => [['password_confirmation' => 'different-password'], 'password'],
]);

it('rejects an email that is already registered', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/auth/register', registerPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
