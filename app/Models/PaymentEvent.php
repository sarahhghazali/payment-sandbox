<?php

namespace App\Models;

use App\Payments\CallbackOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audit record of every callback the merchant app received — accepted or
 * not. Rejected callbacks are the ones worth keeping: they are the evidence
 * when a merchant asks why an order was not marked paid.
 *
 * @property int $id
 * @property string $event_id
 * @property int|null $order_id
 * @property string $order_reference
 * @property string|null $gateway_reference
 * @property string $status
 * @property int|null $amount_cents
 * @property string|null $currency
 * @property CallbackOutcome $outcome
 * @property bool $signature_valid
 * @property array<string, scalar|null> $payload
 * @property Carbon $created_at
 */
class PaymentEvent extends Model
{
    protected $fillable = [
        'event_id',
        'order_id',
        'order_reference',
        'gateway_reference',
        'status',
        'amount_cents',
        'currency',
        'outcome',
        'signature_valid',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => CallbackOutcome::class,
            'signature_valid' => 'boolean',
            'amount_cents' => 'integer',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
