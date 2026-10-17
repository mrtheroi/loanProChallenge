<?php

declare(strict_types=1);

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Ecommerce\Ordering\Domain\Charge;
use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderRepository;
use Ecommerce\Ordering\Domain\PaymentCard;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Catalog\AdminCatalog;
use Tests\Feature\Ordering\Checkout;

uses(RefreshDatabase::class);

it('places an order at database prices, decrements stock and stores only brand and last four', function (): void {
    Checkout::product('MUG-1', '16.99', 10, 'Mug');
    Checkout::product('CUP-1', '5.50', 3, 'Cup');
    $buyer = Checkout::buyer();

    $response = Checkout::place($buyer, Checkout::payload([['sku' => 'mug-1', 'quantity' => 2], ['sku' => 'CUP-1', 'quantity' => 3]]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total', '50.48')
        ->assertJsonPath('data.card', ['brand' => 'visa', 'last4' => '4242'])
        ->assertJsonPath('data.items', [
            ['sku' => 'CUP-1', 'name' => 'Cup', 'unit_price' => '5.50', 'quantity' => 3, 'line_total' => '16.50'],
            ['sku' => 'MUG-1', 'name' => 'Mug', 'unit_price' => '16.99', 'quantity' => 2, 'line_total' => '33.98'],
        ]);

    expect(ProductModel::query()->orderBy('sku')->pluck('stock', 'sku')->all())->toBe(['CUP-1' => 0, 'MUG-1' => 8])
        ->and(DB::table('orders')->where('user_id', $buyer->id)->count())->toBe(1)
        ->and(json_encode(DB::table('orders')->get()))->not->toContain('4242424242424242')
        ->and(json_encode(DB::table('orders')->get()))->not->toContain('"123"');
    expect($response->json('data.id'))->toBeInt();
});

/** @return array<string, mixed> */
function checkoutSnapshot(): array
{
    return [
        'stock' => ProductModel::withTrashed()->orderBy('sku')->pluck('stock', 'sku')->all(),
        'orders' => DB::table('orders')->count(),
        'items' => DB::table('order_items')->count(),
    ];
}

it('rejects the whole order with 409 when any item is short, missing or deleted', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    Checkout::product('CUP-1', '5.50', 1);
    Checkout::product('GONE-1', '1.00', 5);
    ProductModel::query()->where('sku', 'GONE-1')->delete();
    $before = checkoutSnapshot();

    Checkout::place(Checkout::buyer(), Checkout::payload([
        ['sku' => 'MUG-1', 'quantity' => 1],
        ['sku' => 'CUP-1', 'quantity' => 2],
        ['sku' => 'NOPE-1', 'quantity' => 1],
        ['sku' => 'GONE-1', 'quantity' => 1],
    ]))
        ->assertStatus(409)
        ->assertExactJson([
            'message' => 'Some items are not available in the requested quantity.',
            'unavailable' => [
                ['sku' => 'CUP-1', 'requested' => 2, 'available' => 1],
                ['sku' => 'GONE-1', 'requested' => 1, 'available' => 0],
                ['sku' => 'NOPE-1', 'requested' => 1, 'available' => 0],
            ],
        ]);

    expect(checkoutSnapshot())->toBe($before);
});

it('stores nothing and keeps stock when the payment is declined', function (string $number, string $message): void {
    Checkout::product('MUG-1', '16.99', 10);
    $before = checkoutSnapshot();

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'MUG-1', 'quantity' => 2]], ['number' => $number]))
        ->assertStatus(402)
        ->assertExactJson(['message' => $message]);

    expect(checkoutSnapshot())->toBe($before);
})->with([
    'decline card' => ['4000 0000 0000 0002', 'Your card was declined.'],
    'unsupported card' => ['5555555555554444', 'This card is not supported in test mode; use 4242 4242 4242 4242.'],
]);

it('replays the same order for a repeated idempotency key without charging or decrementing twice', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $buyer = Checkout::buyer();
    $payload = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 2]]);
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';

    $first = Checkout::place($buyer, $payload, $key)->assertCreated();
    $second = Checkout::place($buyer, $payload, strtoupper($key))->assertOk();

    expect($second->json())->toBe($first->json())
        ->and(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(8)
        ->and(DB::table('orders')->count())->toBe(1);
});

it('rejects a reused idempotency key with a different request', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 2]]), $key)->assertCreated();

    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 3]]), $key)
        ->assertUnprocessable()
        ->assertJsonPath('errors.idempotency_key', ['This Idempotency-Key was already used with a different request.']);

    expect(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(8);
});

it('scopes idempotency keys to the buyer', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    $payload = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]);

    Checkout::place(Checkout::buyer(), $payload, $key)->assertCreated();
    Checkout::place(Checkout::buyer(), $payload, $key)->assertCreated();

    expect(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(8);
});

it('keeps the prices and names frozen at purchase time', function (): void {
    Checkout::product('MUG-1', '16.99', 10, 'Mug');
    $buyer = Checkout::buyer();
    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]))->assertCreated();

    ProductModel::query()->where('sku', 'MUG-1')->update(['price_cents' => 2500, 'name' => 'Renamed']);

    expect(DB::table('order_items')->first(['name', 'unit_price_cents']))
        ->toEqual((object) ['name' => 'Mug', 'unit_price_cents' => 1699]);
});

it('rejects an invalid checkout request before touching stock', function (array $payload, ?string $key, string $field): void {
    Checkout::product('MUG-1', '16.99', 10);
    $before = checkoutSnapshot();

    test()->actingAs(Checkout::buyer())
        ->postJson('/api/orders', $payload, $key === null ? [] : ['Idempotency-Key' => $key])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(checkoutSnapshot())->toBe($before);
})->with([
    'missing key' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]), null, 'idempotency_key'],
    'key not a uuid' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]), 'abc', 'idempotency_key'],
    'no items' => fn (): array => [Checkout::payload([]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items'],
    'quantity zero' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 0]]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items.0.quantity'],
    'quantity too large' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1000]]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items.0.quantity'],
    'quantity not integer' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => '1.5']]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items.0.quantity'],
    'duplicate sku in another case' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1], ['sku' => 'mug-1', 'quantity' => 1]]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items.1.sku'],
    'malformed sku' => fn (): array => [Checkout::payload([['sku' => 'MUG 1', 'quantity' => 1]]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'items.0.sku'],
    'card number' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], ['number' => '1234']), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'card.number'],
    'expired card' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], ['exp_year' => 2020]), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'card.exp_year'],
    'missing cvc' => fn (): array => [Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], ['cvc' => '']), '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10', 'card.cvc'],
]);

it('only lets buyers place orders', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $payload = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]);
    $headers = ['Idempotency-Key' => '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10'];

    test()->postJson('/api/orders', $payload, $headers)->assertUnauthorized();
    test()->actingAs(AdminCatalog::admin())->postJson('/api/orders', $payload, $headers)->assertForbidden();

    expect(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(10);
});

it('replays the order of a concurrent request that claimed the same key first', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    $payload = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 2]]);
    $winner = Checkout::place($buyer, $payload, $key)->assertCreated()->json();

    $real = app(OrderRepository::class);
    app()->instance(OrderRepository::class, new class($real) implements OrderRepository
    {
        private bool $hidden = true;

        public function __construct(private OrderRepository $real) {}

        public function findByIdempotencyKey(int $userId, string $key): ?Order
        {
            if ($this->hidden) {
                $this->hidden = false;

                return null;
            }

            return $this->real->findByIdempotencyKey($userId, $key);
        }

        public function claim(int $userId, string $key, string $requestHash, PaymentCard $card): int
        {
            return $this->real->claim($userId, $key, $requestHash, $card);
        }

        public function complete(int $orderId, array $lines, Charge $charge): Order
        {
            return $this->real->complete($orderId, $lines, $charge);
        }

        public function findForUser(int $userId, int $orderId): ?Order
        {
            return $this->real->findForUser($userId, $orderId);
        }

        public function pageForUser(int $userId, int $page, int $perPage): array
        {
            return $this->real->pageForUser($userId, $page, $perPage);
        }
    });

    expect(Checkout::place($buyer, $payload, $key)->assertOk()->json())->toBe($winner)
        ->and(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(8)
        ->and(DB::table('orders')->count())->toBe(1);
});

it('throttles checkout attempts per buyer', function (): void {
    Checkout::product('MUG-1', '1.00', 100);
    $buyer = Checkout::buyer();
    $declined = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], ['number' => '4000000000000002']);

    foreach (range(1, 10) as $attempt) {
        Checkout::place($buyer, $declined)->assertStatus(402);
    }

    Checkout::place($buyer, $declined)->assertTooManyRequests();
    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]))->assertCreated();
});

it('rejects two lines that resolve to the same sku even without the global trim', function (): void {
    Checkout::product('MUG-1', '1.00', 10);
    $this->withoutMiddleware(TrimStrings::class);
    $before = checkoutSnapshot();

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1], ['sku' => ' mug-1 ', 'quantity' => 5]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['items.1.sku' => ['repeats the sku of another line']]);

    expect(checkoutSnapshot())->toBe($before);
});

it('handles all-digit skus as text when buying and when reporting shortages', function (): void {
    Checkout::product('12345', '2.00', 1);

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => '12345', 'quantity' => 1]]))
        ->assertCreated()
        ->assertJsonPath('data.items.0.sku', '12345');

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => '12345', 'quantity' => 1], ['sku' => '67890', 'quantity' => 1]]))
        ->assertStatus(409)
        ->assertJsonPath('unavailable', [
            ['sku' => '12345', 'requested' => 1, 'available' => 0],
            ['sku' => '67890', 'requested' => 1, 'available' => 0],
        ]);
});

it('buys a product priced above the 32-bit range', function (): void {
    Checkout::product('BIG-1', '21474836.48', 1);

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'BIG-1', 'quantity' => 1]]))
        ->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', '21474836.48')
        ->assertJsonPath('data.total', '21474836.48');
});

it('rejects an order whose total does not fit before charging', function (): void {
    Checkout::product('HUGE-1', '92233720368547758.07', 5);
    $before = checkoutSnapshot();

    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'HUGE-1', 'quantity' => 2]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.items', ['the order total is too large']);

    expect(checkoutSnapshot())->toBe($before);
});

it('treats the same lines in another order as the same request for numeric skus', function (): void {
    Checkout::product('1', '1.00', 10);
    Checkout::product('01', '1.00', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';

    Checkout::place($buyer, Checkout::payload([['sku' => '1', 'quantity' => 1], ['sku' => '01', 'quantity' => 2]]), $key)->assertCreated();
    Checkout::place($buyer, Checkout::payload([['sku' => '01', 'quantity' => 2], ['sku' => '1', 'quantity' => 1]]), $key)->assertOk();
});

it('compares cards by brand, last four and expiry only, so no card secret is ever stored', function (array $card, int $status): void {
    Checkout::product('MUG-1', '1.00', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]), $key)->assertCreated();

    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], $card), $key)->assertStatus($status);

    expect(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(9);
})->with([
    'same last four and expiry replays' => [['number' => '4000056655664242', 'cvc' => '999'], 200],
    'different last four is another request' => [['number' => '4000000000000077'], 422],
    'different expiry is another request' => [['exp_month' => 11], 422],
]);

it('replays a paid order even after the card has expired', function (): void {
    Checkout::product('MUG-1', '1.00', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    $payload = Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]], ['exp_month' => (int) now()->format('n'), 'exp_year' => (int) now()->format('Y')]);
    $first = Checkout::place($buyer, $payload, $key)->assertCreated()->json();

    $this->travel(2)->months();

    expect(Checkout::place($buyer, $payload, $key)->assertOk()->json())->toBe($first);
    Checkout::place($buyer, $payload)->assertUnprocessable()->assertJsonValidationErrors(['card.exp_year']);
});

it('rejects the order without charging when a price changed since the buyer saw it', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    Checkout::product('CUP-1', '5.50', 10);
    $before = checkoutSnapshot();

    Checkout::place(Checkout::buyer(), Checkout::payload([
        ['sku' => 'MUG-1', 'quantity' => 1, 'unit_price' => '10.00'],
        ['sku' => 'CUP-1', 'quantity' => 1, 'unit_price' => '5.50'],
    ]))
        ->assertStatus(409)
        ->assertExactJson([
            'message' => 'Some prices changed. Review your cart before paying.',
            'price_changes' => [['sku' => 'MUG-1', 'expected' => '10.00', 'current' => '16.99']],
        ]);

    expect(checkoutSnapshot())->toBe($before);
});

it('requires a valid expected unit price for every line', function (array $item): void {
    Checkout::product('MUG-1', '16.99', 10);

    test()->actingAs(Checkout::buyer())
        ->postJson('/api/orders', ['items' => [$item], 'card' => Checkout::payload([])['card']], ['Idempotency-Key' => '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.unit_price']);
})->with([
    'missing' => [['sku' => 'MUG-1', 'quantity' => 1]],
    'not a price' => [['sku' => 'MUG-1', 'quantity' => 1, 'unit_price' => '$16.99']],
]);

it('treats the expected unit price as part of the request identity', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $buyer = Checkout::buyer();
    $key = '0b9f8a52-6c1e-4f43-9a3e-2f1d7c5b8e10';
    $first = Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]), $key)->assertCreated()->json();

    expect(Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1, 'unit_price' => '16.99']]), $key)->assertOk()->json())->toBe($first);
    Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1, 'unit_price' => '15.00']]), $key)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);
});
