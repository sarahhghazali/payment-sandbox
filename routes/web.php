<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\SimulationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchant application
|--------------------------------------------------------------------------
*/
Route::get('/', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/orders', [CheckoutController::class, 'store'])->name('orders.store');
Route::get('/orders/{reference}', [CheckoutController::class, 'show'])->name('orders.show');
// Polled by the order page so a callback appears without a manual refresh.
Route::get('/orders/{reference}/status', [CheckoutController::class, 'status'])->name('orders.status');
// Teaching aid: fire a hostile or duplicate callback at this order.
Route::post('/orders/{reference}/simulate', SimulationController::class)->name('orders.simulate');

// Live view of the queue, delivery attempts and callbacks.
Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
Route::get('/activity/data', [ActivityController::class, 'data'])->name('activity.data');

// Server-to-server: signed by the gateway, so no session and no CSRF token.
Route::post('/callbacks/payment', PaymentCallbackController::class)->name('callbacks.payment');

/*
|--------------------------------------------------------------------------
| Sandbox gateway (stands in for the payment provider)
|--------------------------------------------------------------------------
*/
Route::get('/gateway/pay/{reference}', [GatewayController::class, 'show'])->name('gateway.pay');
Route::post('/gateway/pay/{reference}', [GatewayController::class, 'pay'])->name('gateway.complete');
