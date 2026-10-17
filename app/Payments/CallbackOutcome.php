<?php

namespace App\Payments;

/**
 * Why a callback was accepted or rejected. Stored on every payment event so
 * the dashboard can show exactly what the merchant app decided, and why.
 */
enum CallbackOutcome: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case OrderAlreadyFinal = 'order_already_final';
    case InvalidSignature = 'invalid_signature';
    case Expired = 'expired';
    case UnknownOrder = 'unknown_order';
    case AmountMismatch = 'amount_mismatch';
    case CurrencyMismatch = 'currency_mismatch';

    /** Was the order actually changed by this callback? */
    public function changedOrder(): bool
    {
        return $this === self::Applied;
    }

    /**
     * Callbacks the gateway should stop retrying. A duplicate or an already
     * final order is not an error: the merchant has the payment, so it
     * acknowledges with 200 to end the retry series.
     */
    public function isAcknowledged(): bool
    {
        return match ($this) {
            self::Applied, self::Duplicate, self::OrderAlreadyFinal => true,
            default => false,
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::Applied, self::Duplicate, self::OrderAlreadyFinal => 200,
            self::InvalidSignature, self::Expired => 401,
            self::UnknownOrder => 404,
            self::AmountMismatch, self::CurrencyMismatch => 422,
        };
    }
}
