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

it('stores the email trimmed and lowercased', function (): void {
    $this->postJson('/api/auth/register', registerPayload(['email' => '  Ada@Example.COM ']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.com');

    expect(User::query()->sole()->email)->toBe('ada@example.com');
});

it('rejects an email that differs only by case from a registered one', function (): void {
    $this->postJson('/api/auth/register', registerPayload())->assertCreated();
    auth()->guard('web')->logout();

    $this->postJson('/api/auth/register', registerPayload(['email' => 'ADA@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(User::query()->count())->toBe(1);
});

it('rejects a name that is not valid UTF-8 text', function (string $name): void {
    $this->post('/api/auth/register', registerPayload(['name' => $name]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect(User::query()->count())->toBe(0);
})->with([
    'invalid UTF-8 byte' => [urldecode('%FF')],
    'NUL byte' => ["Ada\0Buyer"],
    'DEL control' => ["Ada\x7FBuyer"],
    'C1 control' => ["Ada\u{0085}Buyer"],
]);

it('accepts a name with accented letters', function (): void {
    $this->postJson('/api/auth/register', registerPayload(['name' => 'Adélaïde Núñez']))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Adélaïde Núñez');
});

it('throttles the eleventh registration attempt within a minute from the same ip', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/auth/register', registerPayload(['name' => '']))->assertUnprocessable();
    }

    $this->postJson('/api/auth/register', registerPayload())->assertTooManyRequests();

    expect(User::query()->count())->toBe(0);
});

it('refuses a registration that does not come from the first-party spa origin', function (?string $referer): void {
    $this->withoutHeader('Referer');

    if ($referer !== null) {
        $this->withHeader('Referer', $referer);
    }

    $this->postJson('/api/auth/register', registerPayload())
        ->assertForbidden()
        ->assertExactJson(['message' => 'Authentication requires the first-party SPA origin.'])
        ->assertCookieMissing(config('session.cookie'));

    $this->assertGuest('web');
    expect(User::query()->count())->toBe(0);
})->with([
    'no origin' => [null],
    'foreign origin' => ['https://evil.example'],
]);
