<?php

declare(strict_types=1);

namespace Ecommerce\Identity;

enum Role: string
{
    case Admin = 'admin';
    case Buyer = 'buyer';
}
