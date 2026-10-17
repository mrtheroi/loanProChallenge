<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Ecommerce\Ordering\Domain\Charge;
use Ecommerce\Ordering\Domain\PaymentCard;
use Ecommerce\Ordering\Domain\PaymentDeclined;
use Ecommerce\Ordering\Domain\PaymentGateway;

final class FakePaymentGateway implements PaymentGateway
{
    private const string APPROVED_CARD = '4242424242424242';

    private const string DECLINED_CARD = '4000000000000002';

    public function charge(PaymentCard $card, int $amountCents): Charge
    {
        return match ($card->number()) {
            self::APPROVED_CARD => new Charge('fake_ch_'.bin2hex(random_bytes(12)), $amountCents),
            self::DECLINED_CARD => throw PaymentDeclined::because('Your card was declined.'),
            default => throw PaymentDeclined::because('This card is not supported in test mode; use 4242 4242 4242 4242.'),
        };
    }
}
