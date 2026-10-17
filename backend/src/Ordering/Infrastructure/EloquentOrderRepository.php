<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Ecommerce\Ordering\Domain\Charge;
use Ecommerce\Ordering\Domain\DuplicateIdempotencyKey;
use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderLine;
use Ecommerce\Ordering\Domain\OrderRepository;
use Ecommerce\Ordering\Domain\PaymentCard;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentOrderRepository implements OrderRepository
{
    public function findByIdempotencyKey(int $userId, string $key): ?Order
    {
        $model = OrderModel::query()->with('items')
            ->where('user_id', $userId)->where('idempotency_key', $key)->where('status', 'paid')
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function claim(int $userId, string $key, string $requestHash, PaymentCard $card): int
    {
        try {
            return OrderModel::query()->create([
                'user_id' => $userId,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'pending',
                'card_brand' => $card->brand(),
                'card_last4' => $card->last4(),
            ])->id;
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateIdempotencyKey;
        }
    }

    public function complete(int $orderId, array $lines, Charge $charge): Order
    {
        $order = OrderModel::query()->findOrFail($orderId);

        foreach ($lines as $line) {
            OrderItemModel::query()->create([
                'order_id' => $orderId,
                'product_id' => $line->productId,
                'sku' => $line->sku,
                'name' => $line->name,
                'unit_price_cents' => $line->unitPriceCents,
                'quantity' => $line->quantity,
                'line_total_cents' => $line->totalCents(),
            ]);
        }

        $order->update(['status' => 'paid', 'total_cents' => $charge->amountCents, 'charge_reference' => $charge->reference]);

        return self::toDomain($order->load('items'));
    }

    public function findForUser(int $userId, int $orderId): ?Order
    {
        $model = OrderModel::query()->with('items')
            ->where('user_id', $userId)->where('status', 'paid')->whereKey($orderId)
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function pageForUser(int $userId, int $page, int $perPage): array
    {
        $query = OrderModel::query()->where('user_id', $userId)->where('status', 'paid');
        $total = $query->count();

        $orders = $query->with('items')->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (OrderModel $model): Order => self::toDomain($model))
            ->all();

        return ['orders' => array_values($orders), 'total' => $total];
    }

    private static function toDomain(OrderModel $model): Order
    {
        return new Order(
            $model->id,
            $model->request_hash,
            $model->status,
            (int) $model->total_cents,
            $model->card_brand,
            $model->card_last4,
            $model->created_at->toDateTimeImmutable(),
            array_values($model->items->map(fn (OrderItemModel $item): OrderLine => new OrderLine(
                $item->product_id, $item->sku, $item->name, $item->unit_price_cents, $item->quantity,
            ))->all()),
        );
    }
}
