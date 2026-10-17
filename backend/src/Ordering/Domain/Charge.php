<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

final readonly class Charge
{
    public function __construct(
        public string $reference,
        public int $amountCents,
    ) {}
}
