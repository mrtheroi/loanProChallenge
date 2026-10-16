<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Domain;

final class ScaledDecimal
{
    public static function parse(string $field, string $value, int $decimals): int
    {
        $pattern = sprintf('/^(?<whole>\d+)(?:\.(?<fraction>\d{1,%d}))?$/D', $decimals);

        if (preg_match($pattern, trim($value), $matches) !== 1) {
            throw InvalidProductAttribute::because(
                $field,
                sprintf('must be a non-negative decimal number with at most %d decimals', $decimals),
            );
        }

        $scaled = (int) $matches['whole'] * 10 ** $decimals + (int) str_pad($matches['fraction'] ?? '', $decimals, '0');

        if (! is_int($scaled)) {
            throw InvalidProductAttribute::because($field, 'is too large');
        }

        return $scaled;
    }

    public static function format(int $scaled, int $decimals): string
    {
        $scale = 10 ** $decimals;

        return sprintf('%d.%0'.$decimals.'d', intdiv($scaled, $scale), $scaled % $scale);
    }
}
