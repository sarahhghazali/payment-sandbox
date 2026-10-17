<?php

namespace App\Http\Controllers;

use App\Models\GatewayPayment;
use App\Payments\GatewaySandbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The gateway's hosted payment page — the screen a customer sees after leaving
 * the merchant's site. Here it lets you choose what the gateway should do,
 * including the awkward cases a real gateway produces by accident.
 */
class GatewayController extends Controller
{
    public function show(string $reference): View
    {
        return view('gateway', [
            'payment' => GatewayPayment::where('reference', $reference)->firstOrFail(),
        ]);
    }

    public function pay(Request $request, string $reference, GatewaySandbox $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:success,failed'],
            'duplicate' => ['nullable', 'boolean'],
        ]);

        $payment = GatewayPayment::where('reference', $reference)->firstOrFail();

        $gateway->completePayment(
            $payment,
            $validated['outcome'],
            copies: ($validated['duplicate'] ?? false) ? 3 : 1,
        );

        return redirect($payment->return_url);
    }
}
