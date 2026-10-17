<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\CallbackOutcome;
use App\Payments\OrderStatus;
use App\Payments\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->order = Order::create([
            'reference' => 'ORD-TEST',
            'description' => 'Test order',
            'amount_cents' => 4990,
            'currency' => 'MYR',
            'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * Build a callback payload, signed unless $signature is overridden.
     *
     * @param  array<string, scalar|null>  $overrides
     * @return array<string, scalar|null>
     */
    private function payload(array $overrides = [], bool $sign = true): array
    {
        $payload = array_merge([
            'event_id' => 'EVT-'.Str::ulid(),
            'gateway_reference' => 'GW-TEST',
            'order_reference' => 'ORD-TEST',
            'amount_cents' => 4990,
            'currency' => 'MYR',
            'status' => 'success',
            'timestamp' => now()->timestamp,
        ], $overrides);

        if ($sign) {
            $payload['signature'] = app(Signature::class)->sign($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, scalar|null>  $payload
     * @return TestResponse<Response>
     */
    private function send(array $payload): TestResponse
    {
        return $this->postJson(route('callbacks.payment'), $payload);
    }

    public function test_a_valid_callback_marks_the_order_paid(): void
    {
        $this->send($this->payload())
            ->assertOk()
            ->assertJson(['outcome' => CallbackOutcome::Applied->value]);

        $this->order->refresh();
        $this->assertSame(OrderStatus::Paid, $this->order->status);
        $this->assertSame('GW-TEST', $this->order->gateway_reference);
        $this->assertNotNull($this->order->paid_at);
    }

    public function test_an_unsigned_callback_cannot_pay_an_order(): void
    {
        $this->send($this->payload(sign: false))
            ->assertStatus(401)
            ->assertJson(['outcome' => CallbackOutcome::InvalidSignature->value]);

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_tampering_with_the_amount_invalidates_the_signature(): void
    {
        // Sign a legitimate payload, then change the amount afterwards —
        // exactly what an attacker on the return URL would try.
        $payload = $this->payload();
        $payload['amount_cents'] = 1;

        $this->send($payload)->assertStatus(401);

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_a_correctly_signed_callback_for_the_wrong_amount_is_rejected(): void
    {
        // Signed by the gateway, but it does not match what the order costs.
        $this->send($this->payload(['amount_cents' => 100]))
            ->assertStatus(422)
            ->assertJson(['outcome' => CallbackOutcome::AmountMismatch->value]);

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_a_callback_in_the_wrong_currency_is_rejected(): void
    {
        $this->send($this->payload(['currency' => 'IDR']))
            ->assertStatus(422)
            ->assertJson(['outcome' => CallbackOutcome::CurrencyMismatch->value]);

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_an_old_callback_is_rejected_as_a_replay(): void
    {
        $this->send($this->payload(['timestamp' => now()->subHour()->timestamp]))
            ->assertStatus(401)
            ->assertJson(['outcome' => CallbackOutcome::Expired->value]);

        $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
    }

    public function test_a_callback_for_an_unknown_order_is_rejected(): void
    {
        $this->send($this->payload(['order_reference' => 'ORD-NOPE']))
            ->assertStatus(404)
            ->assertJson(['outcome' => CallbackOutcome::UnknownOrder->value]);
    }

    public function test_the_same_callback_delivered_five_times_pays_the_order_once(): void
    {
        $payload = $this->payload();

        $this->send($payload)->assertOk()->assertJson(['outcome' => 'applied']);

        for ($i = 0; $i < 4; $i++) {
            $this->send($payload)
                ->assertOk() // 200 so the gateway stops retrying
                ->assertJson(['outcome' => CallbackOutcome::Duplicate->value]);
        }

        $this->order->refresh();
        $this->assertSame(OrderStatus::Paid, $this->order->status);

        // One audit row, because the unique event_id blocked the rest.
        $this->assertSame(1, PaymentEvent::where('order_reference', 'ORD-TEST')->count());
    }

    public function test_a_late_failure_callback_cannot_unpay_a_paid_order(): void
    {
        $this->send($this->payload())->assertOk();

        // A different event, arriving out of order, reporting failure.
        $this->send($this->payload(['status' => 'failed']))
            ->assertOk()
            ->assertJson(['outcome' => CallbackOutcome::OrderAlreadyFinal->value]);

        $this->assertSame(OrderStatus::Paid, $this->order->refresh()->status);
    }

    public function test_a_failed_payment_marks_the_order_failed(): void
    {
        $this->send($this->payload(['status' => 'failed']))->assertOk();

        $this->order->refresh();
        $this->assertSame(OrderStatus::Failed, $this->order->status);
        $this->assertNull($this->order->paid_at);
    }

    public function test_every_callback_is_recorded_for_audit_even_when_rejected(): void
    {
        $this->send($this->payload(sign: false));

        $event = PaymentEvent::firstOrFail();
        $this->assertFalse($event->signature_valid);
        $this->assertSame(CallbackOutcome::InvalidSignature, $event->outcome);
        $this->assertSame('ORD-TEST', $event->order_reference);
    }
}
