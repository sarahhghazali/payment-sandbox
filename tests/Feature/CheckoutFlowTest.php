<?php

namespace Tests\Feature;

use App\Jobs\DeliverCallback;
use App\Models\CallbackDelivery;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Payments\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_order_and_redirects_to_the_gateway(): void
    {
        $response = $this->post(route('orders.store'), [
            'description' => 'Blue widget',
            'amount' => '49.90',
        ]);

        $order = Order::firstOrFail();
        // Decimal input is stored as integer cents.
        $this->assertSame(4990, $order->amount_cents);
        $this->assertSame(OrderStatus::Pending, $order->status);

        $payment = GatewayPayment::firstOrFail();
        $this->assertSame($order->reference, $payment->order_reference);
        $response->assertRedirect(route('gateway.pay', $payment->reference));
    }

    public function test_the_full_flow_pays_the_order(): void
    {
        Http::fake(); // the queued job posts back to the merchant

        $this->post(route('orders.store'), ['description' => 'Widget', 'amount' => '10.00']);
        $payment = GatewayPayment::firstOrFail();

        $this->post(route('gateway.complete', $payment->reference), ['outcome' => 'success'])
            ->assertRedirect(route('orders.show', $payment->order_reference));

        $this->assertSame('success', $payment->refresh()->status);
        $this->assertDatabaseCount('callback_deliveries', 1);

        $delivery = CallbackDelivery::firstOrFail();
        $this->assertTrue($delivery->acknowledged);
        $this->assertSame(1, $delivery->attempt);
    }

    public function test_the_duplicate_option_queues_the_same_event_three_times(): void
    {
        Queue::fake();

        $this->post(route('orders.store'), ['description' => 'Widget', 'amount' => '10.00']);
        $payment = GatewayPayment::firstOrFail();

        $this->post(route('gateway.complete', $payment->reference), [
            'outcome' => 'success',
            'duplicate' => '1',
        ]);

        Queue::assertPushed(DeliverCallback::class, 3);
    }

    public function test_a_rejected_callback_is_retried(): void
    {
        // The merchant answers 401, so the gateway must not treat it as done.
        Http::fake(fn () => Http::response(['outcome' => 'invalid_signature'], 401));

        $this->post(route('orders.store'), ['description' => 'Widget', 'amount' => '10.00']);
        $payment = GatewayPayment::firstOrFail();

        // The queue runs synchronously in tests, so a failed delivery surfaces
        // as a server error on this request. What matters is the record it
        // leaves behind: unacknowledged, so the queue retries it.
        $this->post(route('gateway.complete', $payment->reference), ['outcome' => 'success'])
            ->assertServerError();

        $delivery = CallbackDelivery::firstOrFail();
        $this->assertFalse($delivery->acknowledged);
        $this->assertSame(401, $delivery->http_status);
    }

    public function test_an_invalid_amount_is_rejected_before_an_order_exists(): void
    {
        $this->post(route('orders.store'), ['description' => 'Widget', 'amount' => '-5'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('orders', 0);
    }
}
