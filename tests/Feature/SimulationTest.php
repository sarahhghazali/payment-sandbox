<?php

namespace Tests\Feature;

use App\Http\Controllers\SimulationController;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Payments\CallbackOutcome;
use App\Payments\OrderStatus;
use App\Payments\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The attack buttons on the order page must actually be blocked — a demo that
 * quietly succeeds would teach the wrong lesson.
 */
class SimulationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        return Order::factory()->create(['amount_cents' => 4990, 'currency' => 'MYR']);
    }

    /** @return array<string, array{string, CallbackOutcome}> */
    public static function hostileScenarios(): array
    {
        return [
            'unsigned' => ['forged', CallbackOutcome::InvalidSignature],
            'edited in transit' => ['tampered', CallbackOutcome::InvalidSignature],
            'signed, wrong amount' => ['wrong_amount', CallbackOutcome::AmountMismatch],
            'signed, too old' => ['expired', CallbackOutcome::Expired],
        ];
    }

    #[DataProvider('hostileScenarios')]
    public function test_hostile_callbacks_never_pay_an_order(string $scenario, CallbackOutcome $expected): void
    {
        $order = $this->pendingOrder();

        $this->post(route('orders.simulate', $order->reference), ['scenario' => $scenario])
            ->assertRedirect()
            ->assertSessionHas('simulation.outcome', $expected->value);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame($expected, PaymentEvent::firstOrFail()->outcome);
    }

    public function test_replaying_an_accepted_callback_changes_nothing(): void
    {
        $order = Order::factory()->paid()->create();

        // Seed the event that paid it, as a real flow would have left behind:
        // the whole signed payload, so replaying it is byte for byte identical.
        $payload = [
            'event_id' => 'EVT-ORIGINAL',
            'gateway_reference' => $order->gateway_reference,
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => 'success',
            'timestamp' => now()->timestamp,
        ];
        $payload['signature'] = app(Signature::class)->sign($payload);

        PaymentEvent::create([
            'event_id' => 'EVT-ORIGINAL',
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'status' => 'success',
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'outcome' => CallbackOutcome::Applied,
            'signature_valid' => true,
            'payload' => $payload,
        ]);

        $this->post(route('orders.simulate', $order->reference), ['scenario' => 'replay'])
            ->assertSessionHas('simulation.outcome', CallbackOutcome::Duplicate->value);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_an_unknown_scenario_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->post(route('orders.simulate', $order->reference), ['scenario' => 'drop_database'])
            ->assertSessionHasErrors('scenario');

        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_the_order_page_offers_every_scenario(): void
    {
        $order = $this->pendingOrder();

        $response = $this->get(route('orders.show', $order->reference));

        $response->assertOk();
        foreach (array_keys(SimulationController::SCENARIOS) as $scenario) {
            $response->assertSee('value="'.$scenario.'"', false);
        }
    }
}
