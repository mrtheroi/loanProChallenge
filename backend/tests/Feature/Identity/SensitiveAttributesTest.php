<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sensitiveAttributesAssertHidden(string $body, User $user): void
{
    $secrets = array_filter(['password', 'remember_token', $user->getAuthPassword(), $user->getRememberToken()]);

    foreach ($secrets as $secret) {
        expect($body)->not->toContain($secret);
    }
}

it('never exposes the password hash or remember token', function (): void {
    $this->withHeader('Referer', 'http://localhost:8000');

    $register = $this->postJson('/api/auth/register', [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertCreated();
    $registered = User::query()->where('email', 'ada@example.com')->sole();
    sensitiveAttributesAssertHidden((string) $register->getContent(), $registered);

    $existing = User::factory()->create(['email' => 'existing@example.com', 'password' => 'correct-password']);
    $login = $this->postJson('/api/auth/login', ['email' => 'existing@example.com', 'password' => 'correct-password'])
        ->assertOk();
    sensitiveAttributesAssertHidden((string) $login->getContent(), $existing);

    $me = $this->getJson('/api/auth/me')->assertOk();
    sensitiveAttributesAssertHidden((string) $me->getContent(), $existing);
});
