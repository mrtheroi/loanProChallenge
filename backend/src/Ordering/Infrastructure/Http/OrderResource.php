<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use Ecommerce\Ordering\Domain\Order;
use Ecommerce\Ordering\Domain\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function __construct(private readonly Order $order)
    {
        parent::__construct($order);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order->id,
            'status' => $this->order->status,
            'total' => self::money($this->order->totalCents),
            'card' => ['brand' => $this->order->cardBrand, 'last4' => $this->order->cardLast4],
            'created_at' => $this->order->createdAt->format(DATE_ATOM),
            'items' => array_map(fn (OrderLine $line): array => [
                'sku' => $line->sku,
                'name' => $line->name,
                'unit_price' => self::money($line->unitPriceCents),
                'quantity' => $line->quantity,
                'line_total' => self::money($line->totalCents()),
            ], $this->order->lines),
        ];
    }

    private static function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
