<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\BrowserSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    BrowserSession::persistSessionsInDatabase();
    $this->withHeader('Referer', 'http://localhost:8000');
});

it('logs out, invalidates the session and rotates the csrf token', function (): void {
    User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct-password']);

    $login = $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'correct-password'])
        ->assertOk();
    BrowserSession::carryCookie($this, $login);

    $logout = $this->postJson('/api/auth/logout')->assertNoContent();

    $loginSessionId = $login->getCookie(config('session.cookie'))->getValue();

    expect(DB::table('sessions')->where('id', $loginSessionId)->exists())->toBeFalse()
        ->and($logout->getCookie('XSRF-TOKEN')->getValue())->not->toBe($login->getCookie('XSRF-TOKEN')->getValue());

    BrowserSession::carryCookie($this, $login);
    $this->getJson('/api/auth/me')->assertUnauthorized();

    BrowserSession::carryCookie($this, $logout);
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('rejects a guest logout with 401', function (): void {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});
