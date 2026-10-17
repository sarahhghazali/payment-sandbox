<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallbackDelivery extends Model
{
    protected $fillable = [
        'gateway_payment_id',
        'event_id',
        'attempt',
        'http_status',
        'error',
        'acknowledged',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'http_status' => 'integer',
            'acknowledged' => 'boolean',
        ];
    }

    /** @return BelongsTo<GatewayPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(GatewayPayment::class, 'gateway_payment_id');
    }
}
