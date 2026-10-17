<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

final readonly class Unavailable
{
    public function __construct(
        public string $sku,
        public int $requested,
        public int $available,
    ) {}
}
