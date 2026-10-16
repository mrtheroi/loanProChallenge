<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use Ecommerce\Identity\Role;

final class AdminCatalog
{
    public static function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    public static function buyer(): User
    {
        return User::factory()->create(['role' => Role::Buyer]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Wireless Mouse',
            'sku' => 'wm-001',
            'description' => 'Ergonomic mouse',
            'category' => 'Electronics',
            'price' => '89.99',
            'stock' => 12,
            'weight_kg' => '0.350',
        ], $overrides);
    }
}
