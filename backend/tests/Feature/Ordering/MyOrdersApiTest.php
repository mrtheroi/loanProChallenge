<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\AdminCatalog;
use Tests\Feature\Ordering\Checkout;

uses(RefreshDatabase::class);

it('lists only the buyer own orders, newest first, with pagination metadata', function (): void {
    Checkout::product('MUG-1', '16.99', 10, 'Mug');
    $buyer = Checkout::buyer();
    $first = Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]))->json('data.id');
    $second = Checkout::place($buyer, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 2]]))->json('data.id');
    Checkout::place(Checkout::buyer(), Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]))->assertCreated();

    $this->actingAs($buyer)->getJson('/api/orders?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $second)
        ->assertJsonPath('data.0.items.0', ['sku' => 'MUG-1', 'name' => 'Mug', 'unit_price' => '16.99', 'quantity' => 2, 'line_total' => '33.98'])
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2]);

    $this->actingAs($buyer)->getJson('/api/orders?page=2&per_page=1')->assertJsonPath('data.0.id', $first);
});

it('shows an own order and hides other buyers orders behind a 404', function (): void {
    Checkout::product('MUG-1', '16.99', 10);
    $owner = Checkout::buyer();
    $id = Checkout::place($owner, Checkout::payload([['sku' => 'MUG-1', 'quantity' => 1]]))->json('data.id');

    $this->actingAs($owner)->getJson("/api/orders/{$id}")->assertOk()->assertJsonPath('data.id', $id);
    $this->actingAs(Checkout::buyer())->getJson("/api/orders/{$id}")->assertNotFound()->assertExactJson(['message' => 'Order not found.']);
    $this->actingAs($owner)->getJson('/api/orders/999999')->assertNotFound();
    $this->actingAs($owner)->getJson('/api/orders/abc')->assertNotFound();
});

it('rejects invalid pagination for my orders', function (string $query): void {
    $this->actingAs(Checkout::buyer())->getJson('/api/orders?'.$query)->assertUnprocessable();
})->with(['page=0', 'per_page=51', 'per_page=x']);

it('only lets buyers read orders', function (): void {
    $this->getJson('/api/orders')->assertUnauthorized();
    $this->actingAs(AdminCatalog::admin())->getJson('/api/orders')->assertForbidden();
});

it('falls back to the defaults for empty pagination values', function (): void {
    $this->actingAs(Checkout::buyer())->getJson('/api/orders?page=&per_page=')
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1]);
});

it('returns 404 for an order id beyond the integer range', function (): void {
    $this->actingAs(Checkout::buyer())->getJson('/api/orders/99999999999999999999')->assertNotFound();
});
