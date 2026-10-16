<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Database\Seeder;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::unguarded(fn (): User => User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Demo Admin', 'password' => 'password', 'role' => Role::Admin],
        ));
    }
}
