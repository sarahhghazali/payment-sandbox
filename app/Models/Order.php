<?php

namespace App\Models;

use App\Payments\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $description
 * @property int $amount_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property string|null $gateway_reference
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'description',
        'amount_cents',
        'currency',
        'status',
        'gateway_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->latest('id');
    }

    public function amountForHumans(): string
    {
        return number_format($this->amount_cents / 100, 2).' '.$this->currency;
    }
}
