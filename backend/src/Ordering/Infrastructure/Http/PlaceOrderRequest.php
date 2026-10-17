<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure\Http;

use Ecommerce\Catalog\Domain\InvalidProductAttribute;
use Ecommerce\Catalog\Domain\Money;
use Ecommerce\Catalog\Domain\Sku;
use Ecommerce\Ordering\Application\PlaceOrderCommand;
use Ecommerce\Ordering\Domain\InvalidOrderInput;
use Ecommerce\Ordering\Domain\PaymentCard;
use Illuminate\Foundation\Http\FormRequest;

final class PlaceOrderRequest extends FormRequest
{
    private const int MAX_LINES = 50;

    private const int MAX_QUANTITY = 999;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_LINES],
            'items.*' => ['required', 'array:sku,quantity,unit_price'],
            'items.*.sku' => ['required', 'string', 'distinct:ignore_case'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'items.*.unit_price' => ['required', 'string'],
            'card' => ['required', 'array:number,exp_month,exp_year,cvc'],
            'card.number' => ['required', 'string'],
            'card.exp_month' => ['required', 'integer'],
            'card.exp_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'card.cvc' => ['required', 'string'],
        ];
    }

    public function toCommand(): PlaceOrderCommand
    {
        $key = trim((string) $this->header('Idempotency-Key', ''));

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $key) !== 1) {
            throw InvalidOrderInput::because('idempotency_key', 'the Idempotency-Key header must be a UUID');
        }

        /** @var list<array{sku: string, quantity: int|string, unit_price: string}> $items */
        $items = $this->validated('items');

        $quantities = [];
        $expectedPrices = [];
        foreach ($items as $index => $item) {
            try {
                $sku = Sku::fromString($item['sku'])->value();
            } catch (InvalidProductAttribute $exception) {
                throw InvalidOrderInput::because("items.{$index}.sku", $exception->reason());
            }

            if (array_key_exists($sku, $quantities)) {
                throw InvalidOrderInput::because("items.{$index}.sku", 'repeats the sku of another line');
            }

            try {
                $expectedPrices[$sku] = Money::fromDecimalString($item['unit_price'])->cents();
            } catch (InvalidProductAttribute $exception) {
                throw InvalidOrderInput::because("items.{$index}.unit_price", $exception->reason());
            }

            $quantities[$sku] = (int) $item['quantity'];
        }

        return new PlaceOrderCommand(
            AuthenticatedUser::id($this),
            strtolower($key),
            $quantities,
            $expectedPrices,
            PaymentCard::fromInput(
                $this->string('card.number')->toString(),
                $this->integer('card.exp_month'),
                $this->integer('card.exp_year'),
                $this->string('card.cvc')->toString(),
            ),
            now()->toDateTimeImmutable(),
        );
    }
}
