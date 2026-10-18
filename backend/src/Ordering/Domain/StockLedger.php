<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

interface StockLedger
{
    /**
     * @param  list<string>  $skus
     * @return array<string, StockItem>
     */
    public function lock(array $skus): array;

    /** @param array<int, int> $quantitiesByProductId */
    public function decrement(array $quantitiesByProductId): void;
}
