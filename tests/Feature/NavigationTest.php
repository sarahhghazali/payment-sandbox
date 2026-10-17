<?php

namespace Tests\Feature;

use App\Models\GatewayPayment;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_offers_the_same_menu(): void
    {
        $order = Order::factory()->create();

        $payment = GatewayPayment::create([
            'reference' => 'GW-NAV',
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'callback_url' => route('callbacks.payment'),
            'return_url' => route('orders.show', $order->reference),
        ]);

        $pages = [
            route('checkout'),
            route('activity'),
            route('orders.show', $order->reference),
            route('gateway.pay', $payment->reference),
        ];

        foreach ($pages as $url) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('Payment Sandbox');
            $response->assertSee(route('activity'), false);
        }
    }

    public function test_the_current_page_is_marked_in_the_menu(): void
    {
        // Blade's conditional attribute leaves variable whitespace, so match
        // the link and its marker rather than an exact string.
        $html = (string) $this->get(route('activity'))->getContent();

        $this->assertMatchesRegularExpression(
            '~href="'.preg_quote(route('activity'), '~').'"\s*aria-current="page"~',
            $html,
        );
        // Only the current page is marked: the Orders link is not.
        $this->assertDoesNotMatchRegularExpression(
            '~href="'.preg_quote(route('checkout'), '~').'"\s*aria-current="page"~',
            $html,
        );
    }

    public function test_an_order_page_links_back_to_that_order(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order->reference))
            ->assertSee('Current order');
    }

    public function test_the_gateway_page_says_you_left_the_merchant_site(): void
    {
        $order = Order::factory()->create();

        $payment = GatewayPayment::create([
            'reference' => 'GW-AWAY',
            'order_reference' => $order->reference,
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'callback_url' => route('callbacks.payment'),
            'return_url' => route('orders.show', $order->reference),
        ]);

        // Blade escapes the apostrophe, so match the unambiguous part.
        $this->get(route('gateway.pay', $payment->reference))
            ->assertSee('You are on the gateway');
    }
}
