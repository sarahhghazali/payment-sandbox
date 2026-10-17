<?php

namespace App\Payments;

use App\Jobs\DeliverCallback;
use App\Models\GatewayPayment;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * The fake payment gateway.
 *
 * In a real integration the merchant would POST to the provider's API over
 * HTTPS; here the two sides share one application so the whole flow can be
 * demonstrated and tested without network access. The callback itself IS sent
 * over HTTP, because that is the part worth proving.
 */
class GatewaySandbox
{
    public function __construct(private readonly Signature $signature) {}

    /** Create a payment session and return the hosted payment page URL. */
    public function createSession(Order $order): string
    {
        $payment = GatewayPayment::create([
            'reference' => 'GW-'.strtoupper(Str::ulid()),
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'callback_url' => route('callbacks.payment'),
            'return_url' => route('orders.show', $order->reference),
            'status' => 'pending',
        ]);

        return route('gateway.pay', $payment->reference);
    }

    /**
     * The customer chose an outcome on the hosted page. Sign the callback and
     * queue it for delivery, exactly as a gateway would.
     *
     * @param  'success'|'failed'  $status
     */
    public function completePayment(GatewayPayment $payment, string $status, int $copies = 1): void
    {
        $payment->update(['status' => $status]);

        $payload = $this->signedPayload($payment, $status);

        // copies > 1 simulates a gateway that sends the same event twice:
        // the merchant must still apply it once.
        for ($i = 0; $i < $copies; $i++) {
            DeliverCallback::dispatch($payment->id, $payload);
        }
    }

    /**
     * Build a callback payload with a signature over its canonical form.
     *
     * @return array<string, scalar>
     */
    public function signedPayload(GatewayPayment $payment, string $status): array
    {
        $payload = [
            'event_id' => 'EVT-'.strtoupper(Str::ulid()),
            'gateway_reference' => $payment->reference,
            'order_reference' => $payment->order_reference,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ];

        $payload['signature'] = $this->signature->sign($payload);

        return $payload;
    }
}
