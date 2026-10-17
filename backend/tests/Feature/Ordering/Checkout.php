<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Models\User;
use Ecommerce\Catalog\Application\ProductData;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\ProductRepository;
use Ecommerce\Catalog\Infrastructure\ProductModel;
use Ecommerce\Identity\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

final class Checkout
{
    public static function buyer(): User
    {
        return User::factory()->create(['role' => Role::Buyer]);
    }

    public static function product(string $sku, string $price, int $stock, string $name = 'Product'): void
    {
        app(ProductRepository::class)->add((new ProductData($sku, $name, null, 'Misc', $price, $stock, null))->toProduct());
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, int|string>  $card
     * @return array<string, mixed>
     */
    public static function payload(array $items, array $card = []): array
    {
        return [
            'items' => array_map(self::withCurrentPrice(...), $items),
            'card' => array_merge(['number' => '4242 4242 4242 4242', 'exp_month' => 12, 'exp_year' => (int) now()->addYears(4)->format('Y'), 'cvc' => '123'], $card),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    public static function place(User $buyer, array $payload, ?string $key = null): TestResponse
    {
        return test()->actingAs($buyer)->postJson('/api/orders', $payload, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private static function withCurrentPrice(mixed $item): mixed
    {
        if (! is_array($item) || ! is_string($item['sku'] ?? null) || array_key_exists('unit_price', $item)) {
            return $item;
        }

        $cents = ProductModel::query()->where('sku', strtoupper(trim($item['sku'])))->value('price_cents');

        // An unknown SKU gets any well-formed price: the request must reach the stock check, which rejects it.
        return $item + ['unit_price' => Money::fromCents($cents === null ? 0 : (int) $cents)->toDecimalString()];
    }
}
