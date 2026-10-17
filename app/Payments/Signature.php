<?php

namespace App\Payments;

/**
 * HMAC signing and verification for gateway callbacks.
 *
 * Both sides build the same canonical string from the payload, so a single
 * changed byte (an amount, a status, an order reference) produces a different
 * signature and the callback is rejected.
 */
class Signature
{
    /** Fields that are never part of the signed payload. */
    private const EXCLUDED = ['signature'];

    public function __construct(private readonly string $secret) {}

    /**
     * Build the canonical string: key=value pairs sorted by key, joined by "&".
     *
     * Sorting matters because JSON object order is not guaranteed, so without
     * it the two sides could canonicalise the same payload differently.
     *
     * @param  array<string, scalar|null>  $payload
     */
    public function canonical(array $payload): string
    {
        $payload = array_diff_key($payload, array_flip(self::EXCLUDED));
        ksort($payload);

        $parts = [];
        foreach ($payload as $key => $value) {
            // Cast booleans so true does not become "1" on one side and "true"
            // on the other.
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = $key.'='.(string) $value;
        }

        return implode('&', $parts);
    }

    /** @param  array<string, scalar|null>  $payload */
    public function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonical($payload), $this->secret);
    }

    /**
     * Verify a signature in constant time.
     *
     * hash_equals is required here: a plain === comparison returns faster on an
     * early mismatch, which leaks the correct signature one byte at a time.
     *
     * @param  array<string, scalar|null>  $payload
     */
    public function verify(array $payload, ?string $signature): bool
    {
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }
}
