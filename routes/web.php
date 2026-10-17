<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchant application
|--------------------------------------------------------------------------
*/
Route::get('/', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/orders', [CheckoutController::class, 'store'])->name('orders.store');
Route::get('/orders/{reference}', [CheckoutController::class, 'show'])->name('orders.show');

// Server-to-server: signed by the gateway, so no session and no CSRF token.
Route::post('/callbacks/payment', PaymentCallbackController::class)->name('callbacks.payment');

/*
|--------------------------------------------------------------------------
| Sandbox gateway (stands in for the payment provider)
|--------------------------------------------------------------------------
*/
Route::get('/gateway/pay/{reference}', [GatewayController::class, 'show'])->name('gateway.pay');
Route::post('/gateway/pay/{reference}', [GatewayController::class, 'pay'])->name('gateway.complete');
