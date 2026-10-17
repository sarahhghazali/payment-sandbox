<?php

namespace Database\Factories;

use App\Models\Order;
use App\Payments\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'ORD-'.strtoupper(Str::ulid()),
            'description' => fake()->randomElement([
                'Annual subscription', 'Blue widget', 'Support plan',
                'Event ticket', 'Hardware token',
            ]),
            // Whole cents between 5.00 and 500.00.
            'amount_cents' => fake()->numberBetween(500, 50000),
            'currency' => config('payments.currency'),
            'status' => OrderStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'gateway_reference' => 'GW-'.strtoupper(Str::ulid()),
            'paid_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Failed,
            'gateway_reference' => 'GW-'.strtoupper(Str::ulid()),
        ]);
    }
}
