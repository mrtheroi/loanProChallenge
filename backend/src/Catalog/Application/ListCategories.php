<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Application;

use Ecommerce\Catalog\Domain\CategoryRepository;

final readonly class ListCategories
{
    public function __construct(private CategoryRepository $categories) {}

    /** @return list<string> */
    public function handle(): array
    {
        return $this->categories->namesInUse();
    }
}
