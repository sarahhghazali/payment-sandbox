<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatewayPayment extends Model
{
    protected $fillable = [
        'reference',
        'order_reference',
        'amount_cents',
        'currency',
        'callback_url',
        'return_url',
        'status',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    /** @return HasMany<CallbackDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CallbackDelivery::class);
    }
}
