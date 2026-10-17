<?php

namespace Tests\Unit;

use App\Payments\Signature;
use PHPUnit\Framework\TestCase;

class SignatureTest extends TestCase
{
    private Signature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signature = new Signature('test-secret');
    }

    public function test_key_order_does_not_change_the_signature(): void
    {
        $a = ['amount_cents' => 100, 'currency' => 'MYR', 'status' => 'success'];
        $b = ['status' => 'success', 'amount_cents' => 100, 'currency' => 'MYR'];

        $this->assertSame($this->signature->sign($a), $this->signature->sign($b));
    }

    public function test_the_signature_field_itself_is_not_signed(): void
    {
        $payload = ['order_reference' => 'ORD-1', 'amount_cents' => 100];
        $signed = $payload + ['signature' => 'whatever'];

        $this->assertSame($this->signature->sign($payload), $this->signature->sign($signed));
    }

    public function test_changing_any_value_changes_the_signature(): void
    {
        $payload = ['order_reference' => 'ORD-1', 'amount_cents' => 100];
        $tampered = ['order_reference' => 'ORD-1', 'amount_cents' => 1];

        $this->assertNotSame($this->signature->sign($payload), $this->signature->sign($tampered));
    }

    public function test_a_different_secret_produces_a_different_signature(): void
    {
        $payload = ['order_reference' => 'ORD-1'];

        $this->assertNotSame(
            $this->signature->sign($payload),
            (new Signature('other-secret'))->sign($payload),
        );
    }

    public function test_verify_rejects_empty_and_malformed_signatures(): void
    {
        $payload = ['order_reference' => 'ORD-1'];

        $this->assertFalse($this->signature->verify($payload, null));
        $this->assertFalse($this->signature->verify($payload, ''));
        $this->assertFalse($this->signature->verify($payload, 'not-a-signature'));
        $this->assertTrue($this->signature->verify($payload, $this->signature->sign($payload)));
    }

    public function test_booleans_are_canonicalised_consistently(): void
    {
        $this->assertSame('flag=true', $this->signature->canonical(['flag' => true]));
        $this->assertSame('flag=false', $this->signature->canonical(['flag' => false]));
    }
}
