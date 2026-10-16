<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

use Normalizer;

final class RequiredText
{
    public static function from(string $field, string $value, int $maxLength): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw self::invalidText($field);
        }

        $trimmed = (string) preg_replace('/^\s+|\s+$/u', '', $value);
        $trimmed = (string) Normalizer::normalize($trimmed, Normalizer::FORM_C);

        if (preg_match('/[\x00-\x1F]/', $trimmed) === 1) {
            throw self::invalidText($field);
        }

        if ($trimmed === '') {
            throw InvalidProductAttribute::because($field, 'is required');
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw InvalidProductAttribute::because($field, sprintf('must not exceed %d characters', $maxLength));
        }

        return $trimmed;
    }

    private static function invalidText(string $field): InvalidProductAttribute
    {
        return InvalidProductAttribute::because($field, 'must be valid UTF-8 text without control characters');
    }
}
