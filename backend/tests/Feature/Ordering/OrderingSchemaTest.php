<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Ordering\Checkout;

uses(RefreshDatabase::class);

function insertRawOrder(array $overrides = []): int
{
    return DB::table('orders')->insertGetId(array_merge([
        'user_id' => Checkout::buyer()->id,
        'idempotency_key' => (string) Str::uuid(),
        'request_hash' => str_repeat('a', 64),
        'status' => 'paid',
        'total_cents' => 100,
        'card_brand' => 'visa',
        'card_last4' => '4242',
        'charge_reference' => 'fake_ch_1',
    ], $overrides));
}

it('rejects order rows that the checkout never creates', function (array $overrides, string $constraint): void {
    expect(fn () => insertRawOrder($overrides))->toThrow(QueryException::class, $constraint);
})->with([
    'unknown status' => [['status' => 'shipped'], 'orders_status_valid'],
    'negative total' => [['total_cents' => -1], 'orders_total_non_negative'],
    'paid without total' => [['total_cents' => null], 'orders_paid_is_complete'],
    'paid without charge' => [['charge_reference' => null], 'orders_paid_is_complete'],
]);

it('rejects order items whose numbers do not add up', function (array $overrides, string $constraint): void {
    Checkout::product('MUG-1', '1.00', 1);
    $base = [
        'order_id' => insertRawOrder(),
        'product_id' => DB::table('products')->value('id'),
        'sku' => 'MUG-1',
        'name' => 'Mug',
        'unit_price_cents' => 100,
        'quantity' => 2,
        'line_total_cents' => 200,
    ];

    expect(fn () => DB::table('order_items')->insert(array_merge($base, $overrides)))->toThrow(QueryException::class, $constraint);
})->with([
    'zero quantity' => [['quantity' => 0, 'line_total_cents' => 0], 'order_items_quantity_positive'],
    'negative price' => [['unit_price_cents' => -1, 'line_total_cents' => -2], 'order_items_unit_price_non_negative'],
    'wrong line total' => [['line_total_cents' => 199], 'order_items_line_total_matches'],
]);
