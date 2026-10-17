<?php

declare(strict_types=1);

use Ecommerce\Catalog\Infrastructure\ProductModel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Ordering\Checkout;

const CHILD_TIMEOUT_SECONDS = 30;

uses(DatabaseTruncation::class);

afterEach(function (): void {
    DB::statement('TRUNCATE order_items, orders, products, categories, users RESTART IDENTITY CASCADE');
});

/**
 * @param  list<array{user: int, key: string, sku: string, quantity: int}>  $requests
 * @return list<array<string, mixed>>
 */
function placeConcurrently(array $requests, int $holdMs = 300): array
{
    $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => (string) config('database.connections.pgsql.database')]);
    $processes = [];

    foreach ($requests as $request) {
        $command = [PHP_BINARY, base_path('tests/Concurrency/place-order.php'), (string) $request['user'], $request['key'], $request['sku'], (string) $request['quantity'], (string) $holdMs];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        stream_set_timeout($pipes[1], CHILD_TIMEOUT_SECONDS);
        stream_set_timeout($pipes[2], CHILD_TIMEOUT_SECONDS);
        $processes[] = [$process, $pipes];
    }

    foreach ($processes as [, $pipes]) {
        expect(trim((string) fgets($pipes[2])))->toBe('ready', 'a checkout process did not start in time');
    }

    foreach ($processes as [, $pipes]) {
        fwrite($pipes[0], "go\n");
        fclose($pipes[0]);
    }

    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $output = (string) stream_get_contents($pipes[1]);
        $timedOut = stream_get_meta_data($pipes[1])['timed_out'];
        $errors = $timedOut ? '' : (string) stream_get_contents($pipes[2]);

        if ($timedOut) {
            proc_terminate($process);
        }

        proc_close($process);
        $results[] = $timedOut
            ? ['outcome' => 'timeout']
            : (json_decode($output, true) ?? ['outcome' => 'crash', 'output' => $output.$errors]);
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
