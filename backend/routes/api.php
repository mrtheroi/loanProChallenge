<?php

use Ecommerce\Catalog\Infrastructure\Http\AdminProductController;
use Ecommerce\Identity\Http\Controllers\LoginController;
use Ecommerce\Identity\Http\Controllers\LogoutController;
use Ecommerce\Identity\Http\Controllers\MeController;
use Ecommerce\Identity\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function (): void {
    Route::post('/register', RegisterController::class)->middleware('throttle:register');
    Route::post('/login', LoginController::class)->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', MeController::class);
        Route::post('/logout', LogoutController::class);
    });
});

Route::prefix('admin/products')->middleware(['auth:sanctum', 'can:manage-catalog'])->group(function (): void {
    Route::post('/', [AdminProductController::class, 'store']);
    Route::get('/{sku}', [AdminProductController::class, 'show']);
    Route::put('/{sku}', [AdminProductController::class, 'update']);
    Route::delete('/{sku}', [AdminProductController::class, 'destroy']);
});
