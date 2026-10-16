<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Identity\BrowserSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->bind(ValidateCsrfToken::class, fn (Application $app): ValidateCsrfToken => new class($app, $app->make(Encrypter::class)) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    BrowserSession::persistSessionsInDatabase();
    $this->withHeaders(['Referer' => 'http://localhost:8000', 'Origin' => 'http://localhost:8000']);
    User::factory()->create(['email' => 'csrf@example.com', 'password' => 'correct-password']);
});

it('rejects a stateful login without the XSRF token', function (): void {
    $this->postJson('/api/auth/login', ['email' => 'csrf@example.com', 'password' => 'correct-password'])
        ->assertStatus(419);
});

it('accepts a stateful login that sends the XSRF token from the cookie', function (): void {
    $csrf = $this->get('/sanctum/csrf-cookie')->assertNoContent();
    BrowserSession::carryCookie($this, $csrf);

    $this->withHeader('X-XSRF-TOKEN', $csrf->getCookie('XSRF-TOKEN', false)->getValue())
        ->postJson('/api/auth/login', ['email' => 'csrf@example.com', 'password' => 'correct-password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'csrf@example.com');
});
