<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function usersRoleColumnInsert(array $overrides = []): void
{
    DB::table('users')->insert([
        'name' => 'Raw',
        'email' => 'raw@example.com',
        'password' => 'hash',
        ...$overrides,
    ]);
}

it('defaults the role column to buyer', function (): void {
    usersRoleColumnInsert();

    expect(DB::table('users')->value('role'))->toBe('buyer');
});

it('does not allow a null role', function (): void {
    expect(fn () => usersRoleColumnInsert(['role' => null]))->toThrow(QueryException::class);
});
