<?php

namespace App\Payments;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    /**
     * Paid and Failed are final. A callback that arrives late must never move
     * an order out of a final state — this is what stops a delayed "failed"
     * callback from unpaying an order that already succeeded.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Paid => 'paid',
            self::Failed => 'failed',
        };
    }
}
