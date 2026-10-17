<?php

use App\Providers\AppServiceProvider;
use Ecommerce\Catalog\Infrastructure\CatalogServiceProvider;
use Ecommerce\Identity\IdentityServiceProvider;
use Ecommerce\Import\Infrastructure\ImportServiceProvider;
use Ecommerce\Ordering\Infrastructure\OrderingServiceProvider;

return [
    AppServiceProvider::class,
    CatalogServiceProvider::class,
    IdentityServiceProvider::class,
    ImportServiceProvider::class,
    OrderingServiceProvider::class,
];
