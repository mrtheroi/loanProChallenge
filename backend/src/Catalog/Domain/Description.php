<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final class Description
{
    public static function from(?string $value): ?string
    {
        $description = trim($value ?? '');

        if (! mb_check_encoding($description, 'UTF-8') || preg_match('/(?![\t\n\r])\p{Cc}/u', $description) === 1) {
            throw InvalidProductAttribute::because('description', 'must be valid UTF-8 text without control characters');
        }

        return $description === '' ? null : $description;
    }
}
