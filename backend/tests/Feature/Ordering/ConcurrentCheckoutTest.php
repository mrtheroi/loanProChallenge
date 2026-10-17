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
 * Reads the given pipes until each one closes, or until the deadline passes.
 *
 * @param  array<int, resource>  $streams
 * @return array<int, string>|null null when the deadline passed
 */
function drainUntil(array $streams, float $deadline, bool $untilFirstLine = false): ?array
{
    $buffers = array_fill_keys(array_keys($streams), '');
    foreach ($streams as $stream) {
        stream_set_blocking($stream, false);
    }

    while ($streams !== []) {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            return null;
        }

        $read = $streams;
        $write = $except = null;
        if (stream_select($read, $write, $except, (int) $remaining, (int) (fmod($remaining, 1) * 1_000_000)) === false) {
            return null;
        }

        foreach ($read as $stream) {
            $index = array_search($stream, $streams, true);
            $chunk = (string) fread($stream, 8192);
            $buffers[$index] .= $chunk;

            if ($chunk === '' && feof($stream)) {
                unset($streams[$index]);
            } elseif ($untilFirstLine && str_contains($buffers[$index], "\n")) {
                unset($streams[$index]);
            }
        }
    }

    return $buffers;
}

/**
 * @param  list<array{user: int, key: string, sku: string, quantity: int}>  $requests
 * @return list<array<string, mixed>>
 */
function placeConcurrently(array $requests, int $holdMs = 300): array
{
    $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_DATABASE' => (string) config('database.connections.pgsql.database')]);
    $processes = [];

    foreach ($requests as $request) {
        $priceCents = (string) ProductModel::query()->where('sku', $request['sku'])->value('price_cents');
        $command = [PHP_BINARY, base_path('tests/Concurrency/place-order.php'), (string) $request['user'], $request['key'], $request['sku'], (string) $request['quantity'], $priceCents, (string) $holdMs];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
        $processes[] = [$process, $pipes];
    }

    $deadline = microtime(true) + CHILD_TIMEOUT_SECONDS;

    try {
        foreach ($processes as [, $pipes]) {
            $ready = drainUntil([2 => $pipes[2]], $deadline, untilFirstLine: true);
            expect(trim($ready[2] ?? ''))->toBe('ready', 'a checkout process did not start in time');
        }

        foreach ($processes as [, $pipes]) {
            fwrite($pipes[0], "go\n");
            fclose($pipes[0]);
        }

        $results = [];
        foreach ($processes as [, $pipes]) {
            $output = drainUntil([1 => $pipes[1], 2 => $pipes[2]], $deadline);
            $results[] = $output === null
                ? ['outcome' => 'timeout']
                : (json_decode($output[1], true) ?? ['outcome' => 'crash', 'output' => $output[1].$output[2]]);
        }

        return $results;
    } finally {
        foreach ($processes as [$process]) {
            proc_terminate($process);
            proc_close($process);
        }
    }
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
