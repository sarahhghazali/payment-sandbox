<?php

namespace App\Providers;

use App\Payments\ApplyPaymentCallback;
use App\Payments\Signature;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Signature::class, fn () => new Signature(
            (string) config('payments.secret'),
        ));

        $this->app->singleton(ApplyPaymentCallback::class, fn ($app) => new ApplyPaymentCallback(
            $app->make(Signature::class),
            (int) config('payments.max_age_seconds'),
        ));
    }
}
