<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not expose the stock user route', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/user')
        ->assertNotFound();
});
