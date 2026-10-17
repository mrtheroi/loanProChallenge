<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

use DateTimeImmutable;

final readonly class Order
{
    /** @param list<OrderLine> $lines */
    public function __construct(
        public int $id,
        public string $requestHash,
        public string $status,
        public int $totalCents,
        public string $cardBrand,
        public string $cardLast4,
        public DateTimeImmutable $createdAt,
        public array $lines,
    ) {}
}
