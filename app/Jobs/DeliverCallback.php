<?php

namespace App\Jobs;

use App\Models\CallbackDelivery;
use App\Models\GatewayPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Delivers one callback to the merchant and retries until acknowledged.
 *
 * Real gateways keep retrying on anything that is not a 2xx, which is why the
 * merchant must answer 200 for duplicates too — otherwise a correctly handled
 * payment is retried for hours.
 */
class DeliverCallback implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, scalar>  $payload */
    public function __construct(
        public readonly int $gatewayPaymentId,
        public readonly array $payload,
    ) {}

    public function tries(): int
    {
        return (int) config('payments.delivery.max_attempts');
    }

    /**
     * Wait longer after each failure instead of hammering a struggling
     * merchant: 10s, 30s, 2m, 10m.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('payments.delivery.backoff');
    }

    public function handle(): void
    {
        $payment = GatewayPayment::findOrFail($this->gatewayPaymentId);

        $delivery = CallbackDelivery::create([
            'gateway_payment_id' => $payment->id,
            'event_id' => (string) $this->payload['event_id'],
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = Http::timeout((int) config('payments.delivery.timeout'))
                ->asJson()
                ->post($payment->callback_url, $this->payload);
        } catch (Throwable $e) {
            $delivery->update(['error' => $e->getMessage()]);

            throw $e; // let the queue retry with backoff
        }

        $delivery->update([
            'http_status' => $response->status(),
            'acknowledged' => $response->successful(),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Merchant did not acknowledge callback (HTTP {$response->status()})"
            );
        }
    }
}
