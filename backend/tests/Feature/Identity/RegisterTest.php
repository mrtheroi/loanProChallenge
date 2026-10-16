<?php

declare(strict_types=1);

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('rejects a password longer than 72 bytes', function (string $password): void {
    $this->postJson('/api/auth/register', registerPayload(['password' => $password, 'password_confirmation' => $password]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
})->with([
    '73 ascii bytes' => [str_repeat('a', 73)],
    '40 multibyte characters in 80 bytes' => [str_repeat('é', 40)],
]);

it('accepts a password of exactly 72 bytes', function (): void {
    $password = str_repeat('é', 36);

    $this->postJson('/api/auth/register', registerPayload(['password' => $password, 'password_confirmation' => $password]))
        ->assertCreated();
});

it('returns the email validation error when a concurrent registration takes the email first', function (): void {
    User::creating(function (User $user): void {
        DB::table('users')->insert([
            'name' => 'Concurrent Buyer',
            'email' => $user->email,
            'password' => 'hashed-elsewhere',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->postJson('/api/auth/register', registerPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
});
