<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\GatewaySandbox;
use App\Payments\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(): View
    {
        return view('checkout', [
            'orders' => Order::latest('id')->limit(20)->get(),
            'currency' => config('payments.currency'),
        ]);
    }

    public function store(Request $request, GatewaySandbox $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:120'],
            // Collected as a decimal for humans, stored as integer cents.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);

        $order = Order::create([
            'reference' => 'ORD-'.strtoupper(Str::ulid()),
            'description' => $validated['description'],
            'amount_cents' => (int) round($validated['amount'] * 100),
            'currency' => config('payments.currency'),
            'status' => OrderStatus::Pending,
        ]);

        return redirect()->away($gateway->createSession($order));
    }

    public function show(string $reference): View
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        return view('order', [
            'order' => $order,
            'events' => $order->events()->get(),
        ]);
    }
}
