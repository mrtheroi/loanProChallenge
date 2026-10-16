<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Identity\BrowserSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    BrowserSession::persistSessionsInDatabase();
    $this->withHeader('Referer', 'http://localhost:8000');
});

it('keeps the registered buyer signed in through the session cookie', function (): void {
    $registered = $this->postJson('/api/auth/register', [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertCreated();

    BrowserSession::carryCookie($this, $registered);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertExactJson($registered->json());
});

it('rejects a guest with 401', function (): void {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});
