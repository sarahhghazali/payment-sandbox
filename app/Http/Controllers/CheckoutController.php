<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\GatewaySandbox;
use App\Payments\OrderStatus;
use Illuminate\Http\JsonResponse;
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
            'scenarios' => SimulationController::SCENARIOS,
        ]);
    }

    /**
     * The order's current state as JSON.
     *
     * The page polls this so a callback arriving in the queue worker shows up
     * without the visitor having to refresh — which is the whole point of a
     * demonstration.
     */
    public function status(string $reference): JsonResponse
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        return response()->json([
            'status' => $order->status->value,
            'badge' => $order->status->badgeClass(),
            'gateway_reference' => $order->gateway_reference,
            'paid_at' => $order->paid_at?->toDayDateTimeString(),
            'events' => $order->events()->get()->map(fn ($event) => [
                'outcome' => $event->outcome->value,
                'event_id' => $event->event_id,
                'status' => $event->status,
                'amount' => $event->amount_cents !== null
                    ? number_format($event->amount_cents / 100, 2).' '.$event->currency
                    : null,
                'signature_valid' => $event->signature_valid,
                'when' => $event->created_at->diffForHumans(),
            ])->all(),
        ])->header('Cache-Control', 'no-store');
    }
}
