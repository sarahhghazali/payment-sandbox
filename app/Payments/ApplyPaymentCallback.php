<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Decides what a gateway callback is allowed to do to an order.
 *
 * Every rule here exists because skipping it loses real money:
 *
 *  - signature   an attacker could POST "status=success" for any order
 *  - freshness   an old captured callback could be replayed later
 *  - amount      a customer could pay 1.00 for a 100.00 order
 *  - currency    100 IDR is not 100 MYR
 *  - idempotency gateways retry; an order must be paid once, not five times
 *  - final state a late "failed" must not undo a successful payment
 */
class ApplyPaymentCallback
{
    public function __construct(
        private readonly Signature $signature,
        private readonly int $maxAgeSeconds,
    ) {}

    /**
     * @param  array<string, scalar|null>  $payload
     */
    public function handle(array $payload, ?string $signature): CallbackOutcome
    {
        if (! $this->signature->verify($payload, $signature)) {
            return $this->record($payload, CallbackOutcome::InvalidSignature, false);
        }

        if ($this->isStale($payload)) {
            return $this->record($payload, CallbackOutcome::Expired, true);
        }

        $reference = (string) ($payload['order_reference'] ?? '');
        $order = Order::where('reference', $reference)->first();

        if (! $order) {
            return $this->record($payload, CallbackOutcome::UnknownOrder, true);
        }

        if ((int) ($payload['amount_cents'] ?? 0) !== $order->amount_cents) {
            return $this->record($payload, CallbackOutcome::AmountMismatch, true, $order);
        }

        if (($payload['currency'] ?? null) !== $order->currency) {
            return $this->record($payload, CallbackOutcome::CurrencyMismatch, true, $order);
        }

        return $this->apply($payload, $order);
    }

    /**
     * Apply the callback inside a transaction.
     *
     * The order row is locked first so two deliveries arriving at the same
     * instant are serialised, and the unique index on payment_events.event_id
     * is what actually guarantees the work happens once.
     *
     * @param  array<string, scalar|null>  $payload
     */
    private function apply(array $payload, Order $order): CallbackOutcome
    {
        return DB::transaction(function () use ($payload, $order) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->first();

            try {
                $event = $this->newEvent($payload, CallbackOutcome::Applied, true, $order);
                $event->save();
            } catch (QueryException $e) {
                // Unique violation on event_id: this exact callback was already
                // processed, so the gateway is retrying. Nothing left to do.
                return CallbackOutcome::Duplicate;
            }

            if ($order->status->isFinal()) {
                $event->update(['outcome' => CallbackOutcome::OrderAlreadyFinal]);

                return CallbackOutcome::OrderAlreadyFinal;
            }

            $paid = ($payload['status'] ?? null) === 'success';

            $order->update([
                'status' => $paid ? OrderStatus::Paid : OrderStatus::Failed,
                'gateway_reference' => $payload['gateway_reference'] ?? null,
                'paid_at' => $paid ? now() : null,
            ]);

            return CallbackOutcome::Applied;
        });
    }

    /** @param  array<string, scalar|null>  $payload */
    private function isStale(array $payload): bool
    {
        $timestamp = $payload['timestamp'] ?? null;

        if (! is_numeric($timestamp)) {
            return true;
        }

        return Carbon::createFromTimestamp((int) $timestamp)
            ->diffInSeconds(now(), absolute: true) > $this->maxAgeSeconds;
    }

    /** @param  array<string, scalar|null>  $payload */
    private function record(
        array $payload,
        CallbackOutcome $outcome,
        bool $signatureValid,
        ?Order $order = null,
    ): CallbackOutcome {
        $event = $this->newEvent($payload, $outcome, $signatureValid, $order);

        try {
            $event->save();
        } catch (QueryException $e) {
            // A rejected callback replayed with the same event id. The audit
            // trail already has it; keep the original outcome.
        }

        return $outcome;
    }

    /** @param  array<string, scalar|null>  $payload */
    private function newEvent(
        array $payload,
        CallbackOutcome $outcome,
        bool $signatureValid,
        ?Order $order = null,
    ): PaymentEvent {
        return new PaymentEvent([
            // An unsigned payload may not carry an event id at all, so fall
            // back to a generated one to keep the audit row insertable.
            'event_id' => (string) ($payload['event_id'] ?? 'unsigned-'.Str::ulid()),
            'order_id' => $order?->getKey(),
            'order_reference' => (string) ($payload['order_reference'] ?? ''),
            'gateway_reference' => $payload['gateway_reference'] ?? null,
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'amount_cents' => isset($payload['amount_cents']) ? (int) $payload['amount_cents'] : null,
            'currency' => $payload['currency'] ?? null,
            'outcome' => $outcome,
            'signature_valid' => $signatureValid,
            'payload' => $payload,
        ]);
    }
}
