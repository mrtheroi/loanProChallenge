<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string $status
 * @property int|null $total_cents
 * @property string $card_brand
 * @property string $card_last4
 * @property string|null $charge_reference
 * @property Carbon $created_at
 * @property-read Collection<int, OrderItemModel> $items
 */
final class OrderModel extends Model
{
    protected $table = 'orders';

    protected $fillable = ['user_id', 'idempotency_key', 'request_hash', 'status', 'total_cents', 'card_brand', 'card_last4', 'charge_reference'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'total_cents' => 'integer'];
    }

    /** @return HasMany<OrderItemModel, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItemModel::class, 'order_id')->orderBy('sku');
    }
}
