<?php

use Illuminate\Support\Facades\DB;

it('connects to the postgres test database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('ecommerce_test')
        ->and(DB::selectOne('select 1 as one')->one)->toBe(1);
});
