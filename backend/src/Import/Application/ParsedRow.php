<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

use Ecommerce\Catalog\Domain\Product;

final readonly class ParsedRow
{
    /** @param  array<string, list<string>>  $errors */
    public function __construct(
        public string $sku,
        public ?Product $product,
        public array $errors,
        public string $signature,
    ) {}
}
