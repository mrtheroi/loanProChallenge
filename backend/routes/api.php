<?php

use Ecommerce\Identity\Http\Controllers\LoginController;
use Ecommerce\Identity\Http\Controllers\LogoutController;
use Ecommerce\Identity\Http\Controllers\MeController;
use Ecommerce\Identity\Http\Controllers\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function (): void {
    Route::post('/register', RegisterController::class);
    Route::post('/login', LoginController::class)->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', MeController::class);
        Route::post('/logout', LogoutController::class);
    });
});
