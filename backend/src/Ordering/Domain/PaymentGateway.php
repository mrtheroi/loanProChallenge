<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Domain;

interface PaymentGateway
{
    /** @throws PaymentDeclined */
    public function charge(PaymentCard $card, int $amountCents): Charge;
}
