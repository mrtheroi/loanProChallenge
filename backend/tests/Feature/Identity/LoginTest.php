<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Identity\BrowserSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    BrowserSession::persistSessionsInDatabase();
    $this->withHeader('Referer', 'http://localhost:8000');
});

function loginTestAdmin(): User
{
    return User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'correct-password']);
}

it('logs in with valid credentials and keeps the session', function (): void {
    $admin = loginTestAdmin();

    $login = $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'correct-password'])
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]]);

    BrowserSession::carryCookie($this, $login);

    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'admin');
});

it('regenerates the session id and the csrf token on login', function (): void {
    loginTestAdmin();
    $cookieName = config('session.cookie');

    $guest = $this->get('/sanctum/csrf-cookie')->assertNoContent();
    BrowserSession::carryCookie($this, $guest);

    $login = $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'correct-password'])
        ->assertOk();

    expect($login->getCookie($cookieName)->getValue())->not->toBe($guest->getCookie($cookieName)->getValue())
        ->and($login->getCookie('XSRF-TOKEN')->getValue())->not->toBe($guest->getCookie('XSRF-TOKEN')->getValue());
});

it('rejects invalid credentials with the same generic message', function (string $email, string $password): void {
    loginTestAdmin();

    $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('auth.failed'),
            'errors' => ['email' => [__('auth.failed')]],
        ]);

    $this->getJson('/api/auth/me')->assertUnauthorized();
})->with([
    'wrong password' => ['admin@example.com', 'wrong-password'],
    'unknown email' => ['nobody@example.com', 'correct-password'],
]);

it('throttles the sixth login attempt within a minute for the same email and ip', function (): void {
    loginTestAdmin();
    $credentials = ['email' => 'admin@example.com', 'password' => 'wrong-password'];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', $credentials)->assertTooManyRequests();
    $this->postJson('/api/auth/login', ['email' => 'other@example.com', 'password' => 'wrong-password'])
        ->assertUnprocessable();
});

it('logs in when the email differs in case and surrounding spaces', function (): void {
    loginTestAdmin();

    $this->postJson('/api/auth/login', ['email' => '  Admin@Example.COM ', 'password' => 'correct-password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@example.com');
});

it('throttles login attempts by the normalized email', function (): void {
    loginTestAdmin();

    foreach (['admin@example.com', 'Admin@Example.com', ' ADMIN@example.com', 'admin@EXAMPLE.com ', 'aDmIn@example.com'] as $email) {
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong-password'])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['email' => '  Admin@Example.COM ', 'password' => 'correct-password'])
        ->assertTooManyRequests();
});

it('throttles by the trimmed email even without the global trim middleware', function (): void {
    loginTestAdmin();
    $this->withoutMiddleware(TrimStrings::class);

    foreach (['admin@example.com', ' admin@example.com', 'admin@example.com ', '  admin@example.com', 'admin@example.com  '] as $email) {
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong-password'])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['email' => ' admin@example.com ', 'password' => 'correct-password'])
        ->assertTooManyRequests();
});

it('rejects a non-string email with a validation error', function (): void {
    $this->postJson('/api/auth/login', ['email' => ['a'], 'password' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('throttles one ip that sprays login attempts across many emails', function (): void {
    foreach (range(1, 20) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => "user{$attempt}@example.com", 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['email' => 'user21@example.com', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

it('refuses a login that does not come from the first-party spa origin', function (?string $referer): void {
    loginTestAdmin();
    $this->withoutHeader('Referer');

    if ($referer !== null) {
        $this->withHeader('Referer', $referer);
    }

    $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'correct-password'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Authentication requires the first-party SPA origin.'])
        ->assertCookieMissing(config('session.cookie'));

    $this->assertGuest('web');
})->with([
    'no origin' => [null],
    'foreign origin' => ['https://evil.example'],
]);
