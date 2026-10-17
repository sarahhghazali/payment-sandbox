<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\ApplyPaymentCallback;
use App\Payments\Signature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Fires the awkward callbacks from the order page, so the protections can be
 * demonstrated in a browser instead of with curl.
 *
 * The callback is handed to the same ApplyPaymentCallback the real endpoint
 * uses, so what you see here is exactly what a gateway would get back.
 */
class SimulationController extends Controller
{
    /** Human readable description of each scenario, shown on the order page. */
    public const SCENARIOS = [
        'forged' => 'Unsigned callback claiming the order was paid',
        'tampered' => 'Valid signature, then the amount was edited in transit',
        'wrong_amount' => 'Correctly signed, but for the wrong amount',
        'expired' => 'Correctly signed, but an hour old (a replay)',
        'replay' => 'The last accepted callback, delivered again',
    ];

    public function __invoke(
        Request $request,
        string $reference,
        Signature $signer,
        ApplyPaymentCallback $callback,
    ): RedirectResponse {
        $validated = $request->validate([
            'scenario' => ['required', 'in:'.implode(',', array_keys(self::SCENARIOS))],
        ]);

        $order = Order::where('reference', $reference)->firstOrFail();
        $payload = $this->buildPayload($validated['scenario'], $order, $signer);

        $outcome = $callback->handle($payload, $payload['signature'] ?? null);

        return back()->with('simulation', [
            'scenario' => self::SCENARIOS[$validated['scenario']],
            'outcome' => $outcome->value,
            'status' => $outcome->httpStatus(),
            'changed' => $outcome->changedOrder(),
        ]);
    }

    /** @return array<string, scalar|null> */
    private function buildPayload(string $scenario, Order $order, Signature $signer): array
    {
        // What an honest gateway would send for this order.
        $base = [
            'event_id' => 'EVT-'.strtoupper(Str::ulid()),
            'gateway_reference' => $order->gateway_reference ?? 'GW-SIMULATED',
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => 'success',
            'timestamp' => now()->timestamp,
        ];

        return match ($scenario) {
            // No signature at all: the crudest forgery.
            'forged' => $base,

            // Signed honestly, then the amount is changed — so the signature
            // no longer matches the payload.
            'tampered' => [...$base, 'signature' => $signer->sign($base), 'amount_cents' => 1],

            // Genuinely signed by the gateway, but for the wrong amount. The
            // signature is valid, so only the amount check catches this.
            'wrong_amount' => $this->signed([...$base, 'amount_cents' => 100], $signer),

            // Correctly signed but stale, as a captured callback would be.
            'expired' => $this->signed([...$base, 'timestamp' => now()->subHour()->timestamp], $signer),

            // The exact callback that was already applied, sent again.
            'replay' => $this->lastAppliedPayload($order) ?? $this->signed($base, $signer),

            // Unreachable: the request is validated against SCENARIOS first.
            default => throw new \InvalidArgumentException("Unknown scenario [{$scenario}]"),
        };
    }

    /**
     * @param  array<string, scalar|null>  $payload
     * @return array<string, scalar|null>
     */
    private function signed(array $payload, Signature $signer): array
    {
        return [...$payload, 'signature' => $signer->sign($payload)];
    }

    /** @return array<string, scalar|null>|null */
    private function lastAppliedPayload(Order $order): ?array
    {
        $event = $order->events()->where('outcome', 'applied')->first();

        return $event?->payload;
    }
}
