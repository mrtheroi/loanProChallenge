<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Ecommerce\Identity\Role;
use Illuminate\Database\Seeder;

final class DemoBuyerSeeder extends Seeder
{
    public function run(): void
    {
        User::unguarded(fn (): User => User::query()->updateOrCreate(
            ['email' => 'buyer@example.com'],
            ['name' => 'Demo Buyer', 'password' => 'password', 'role' => Role::Buyer],
        ));
    }
}
