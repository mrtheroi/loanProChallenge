<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use Closure;

interface TransactionRunner
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}
