<?php

use App\Providers\AppServiceProvider;
use Ecommerce\Catalog\Infrastructure\CatalogServiceProvider;
use Ecommerce\Identity\IdentityServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    IdentityServiceProvider::class,
];
