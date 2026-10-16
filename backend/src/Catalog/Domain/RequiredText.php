<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final class RequiredText
{
    public static function from(string $field, string $value, int $maxLength): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidProductAttribute::because($field, 'is required');
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw InvalidProductAttribute::because($field, sprintf('must not exceed %d characters', $maxLength));
        }

        return $trimmed;
    }
}
