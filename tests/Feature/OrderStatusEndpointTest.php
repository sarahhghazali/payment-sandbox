<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\CallbackOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order page polls this endpoint, so a callback delivered by the queue
 * worker shows up without a manual refresh.
 */
class OrderStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_a_pending_order_with_no_events(): void
    {
        $order = Order::factory()->create();

        $this->getJson(route('orders.status', $order->reference))
            ->assertOk()
            ->assertJson([
                'status' => 'pending',
                'gateway_reference' => null,
                'paid_at' => null,
                'events' => [],
            ]);
    }

    public function test_it_reports_a_paid_order_and_its_callbacks(): void
    {
        $order = Order::factory()->paid()->create();

        PaymentEvent::create([
            'event_id' => 'EVT-1',
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'status' => 'success',
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'outcome' => CallbackOutcome::Applied,
            'signature_valid' => true,
            'payload' => [],
        ]);

        $this->getJson(route('orders.status', $order->reference))
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('badge', 'paid')
            ->assertJsonPath('events.0.outcome', 'applied')
            ->assertJsonPath('events.0.signature_valid', true);
    }

    public function test_an_unknown_order_is_not_found(): void
    {
        $this->getJson(route('orders.status', 'ORD-NOPE'))->assertNotFound();
    }
}
