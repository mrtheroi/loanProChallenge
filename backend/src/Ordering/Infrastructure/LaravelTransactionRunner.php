<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Closure;
use Ecommerce\Ordering\Domain\TransactionRunner;
use Illuminate\Support\Facades\DB;

final class LaravelTransactionRunner implements TransactionRunner
{
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
