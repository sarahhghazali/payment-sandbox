<?php

namespace App\Models;

use App\Payments\CallbackOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit record of every callback the merchant app received — accepted or
 * not. Rejected callbacks are the ones worth keeping: they are the evidence
 * when a merchant asks why an order was not marked paid.
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
