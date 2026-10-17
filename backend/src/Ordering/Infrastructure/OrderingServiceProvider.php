<?php

declare(strict_types=1);

namespace Ecommerce\Ordering\Infrastructure;

use Ecommerce\Ordering\Domain\OrderRepository;
use Ecommerce\Ordering\Domain\PaymentGateway;
use Ecommerce\Ordering\Domain\StockLedger;
use Ecommerce\Ordering\Domain\TransactionRunner;
use Ecommerce\Ordering\Infrastructure\Http\AuthenticatedUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class OrderingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        OrderRepository::class => EloquentOrderRepository::class,
        PaymentGateway::class => FakePaymentGateway::class,
        StockLedger::class => EloquentStockLedger::class,
        TransactionRunner::class => LaravelTransactionRunner::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)->by('user:'.AuthenticatedUser::id($request)));
    }
}
