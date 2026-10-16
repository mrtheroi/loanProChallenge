<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function insertRawUserWithEmail(string $email): void
{
    DB::table('users')->insert([
        'name' => 'Raw User',
        'email' => $email,
        'password' => 'hashed-elsewhere',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('rejects a raw insert of an email that differs only by case', function (): void {
    insertRawUserWithEmail('ada@example.com');

    expect(fn () => insertRawUserWithEmail('Ada@Example.com'))
        ->toThrow(UniqueConstraintViolationException::class);
});
