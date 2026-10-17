<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $sku
 * @property string $name
 * @property int $unit_price_cents
 * @property int $quantity
 * @property int $line_total_cents
 */
final class OrderItemModel extends Model
{
    public $timestamps = false;

    protected $table = 'order_items';

    protected $fillable = ['order_id', 'product_id', 'sku', 'name', 'unit_price_cents', 'quantity', 'line_total_cents'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['product_id' => 'integer', 'unit_price_cents' => 'integer', 'quantity' => 'integer', 'line_total_cents' => 'integer'];
    }
}
