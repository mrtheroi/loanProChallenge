<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

interface OrderRepository
{
    public function findByIdempotencyKey(int $userId, string $key): ?Order;

    /** @throws DuplicateIdempotencyKey */
    public function claim(int $userId, string $key, string $requestHash, PaymentCard $card): int;

    /** @param list<OrderLine> $lines */
    public function complete(int $orderId, array $lines, Charge $charge): Order;

    public function findForUser(int $userId, int $orderId): ?Order;

    /** @return array{orders: list<Order>, total: int} */
    public function pageForUser(int $userId, int $page, int $perPage): array;
}
