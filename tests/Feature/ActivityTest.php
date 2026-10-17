<?php

namespace Tests\Feature;

use App\Models\CallbackDelivery;
use App\Models\GatewayPayment;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\CallbackOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_activity_page_loads_when_nothing_has_happened(): void
    {
        $this->get(route('activity'))
            ->assertOk()
            ->assertSee('Live activity');
    }

    public function test_the_data_endpoint_reports_queue_deliveries_and_callbacks(): void
    {
        $order = Order::factory()->create();

        $payment = GatewayPayment::create([
            'reference' => 'GW-1',
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'callback_url' => 'http://localhost/callbacks/payment',
            'return_url' => 'http://localhost/orders/'.$order->reference,
        ]);

        CallbackDelivery::create([
            'gateway_payment_id' => $payment->id,
            'event_id' => 'EVT-1',
            'attempt' => 1,
            'http_status' => 200,
            'acknowledged' => true,
        ]);

        PaymentEvent::create([
            'event_id' => 'EVT-1',
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'status' => 'success',
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'outcome' => CallbackOutcome::Applied,
            'signature_valid' => true,
            'payload' => ['event_id' => 'EVT-1'],
        ]);

        $response = $this->getJson(route('activity.data'))->assertOk();

        $response->assertJsonPath('deliveries.0.order', $order->reference);
        $response->assertJsonPath('deliveries.0.acknowledged', true);
        $response->assertJsonPath('callbacks.0.outcome', 'applied');
        $response->assertJsonPath('callbacks.0.accepted', true);
        $response->assertJsonPath('queue.waiting', 0);
    }

    public function test_the_data_endpoint_is_never_cached(): void
    {
        // Status changes constantly; a cached copy would show stale state.
        // Laravel appends "private" of its own accord, so check for no-store.
        $header = $this->getJson(route('activity.data'))->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', (string) $header);
    }
}
