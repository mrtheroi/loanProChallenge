<?php

use App\Providers\AppServiceProvider;
use Ecommerce\Catalog\Infrastructure\CatalogServiceProvider;
use Ecommerce\Identity\IdentityServiceProvider;
use Ecommerce\Import\Infrastructure\ImportServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    IdentityServiceProvider::class,
    ImportServiceProvider::class,
];
