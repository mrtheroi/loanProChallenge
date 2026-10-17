<?php

declare(strict_types=1);

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Ordering\Checkout;

uses(DatabaseTruncation::class);

/**
 * @param  list<array{user: int, key: string, sku: string, quantity: int}>  $requests
 * @return list<array<string, mixed>>
 */
function placeConcurrently(array $requests, int $holdMs = 300): array
{
    $startAt = microtime(true) + 1.5;
    $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => (string) config('database.connections.pgsql.database')]);
    $processes = [];

    foreach ($requests as $request) {
        $command = [PHP_BINARY, base_path('tests/Concurrency/place-order.php'), (string) $request['user'], $request['key'], $request['sku'], (string) $request['quantity'], (string) $holdMs, sprintf('%.6f', $startAt)];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $processes[] = [$process, $pipes];
    }

    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        proc_close($process);
        $results[] = json_decode($output, true) ?? ['outcome' => 'crash', 'output' => $output.$errors];
    }

    return $results;
}

it('sells the last unit exactly once when two buyers check out at the same time', function (): void {
    Checkout::product('LAST-1', '10.00', 1);

    $results = placeConcurrently([
        ['user' => Checkout::buyer()->id, 'key' => (string) Str::uuid(), 'sku' => 'LAST-1', 'quantity' => 1],
        ['user' => Checkout::buyer()->id, 'key' => (string) Str::uuid(), 'sku' => 'LAST-1', 'quantity' => 1],
    ]);

    $outcomes = array_column($results, 'outcome');
    sort($outcomes);

    expect($outcomes)->toBe(['created', 'insufficient'], json_encode($results))
        ->and(ProductModel::query()->where('sku', 'LAST-1')->value('stock'))->toBe(0)
        ->and(DB::table('orders')->count())->toBe(1);
});

it('creates one order when the same idempotency key is sent twice at the same time', function (): void {
    Checkout::product('MUG-1', '10.00', 5);
    $buyer = Checkout::buyer()->id;
    $key = (string) Str::uuid();

    $results = placeConcurrently([
        ['user' => $buyer, 'key' => $key, 'sku' => 'MUG-1', 'quantity' => 2],
        ['user' => $buyer, 'key' => $key, 'sku' => 'MUG-1', 'quantity' => 2],
    ]);

    $outcomes = array_column($results, 'outcome');
    sort($outcomes);

    expect($outcomes)->toBe(['created', 'replayed'], json_encode($results))
        ->and(array_unique(array_column($results, 'order')))->toHaveCount(1)
        ->and(ProductModel::query()->where('sku', 'MUG-1')->value('stock'))->toBe(3)
        ->and(DB::table('orders')->count())->toBe(1);
});
