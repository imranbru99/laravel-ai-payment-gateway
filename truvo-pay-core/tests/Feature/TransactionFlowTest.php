<?php

namespace Truvo\Pay\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Truvo\Pay\Events\PaymentCompleted;
use Truvo\Pay\Events\PaymentFailed;
use Truvo\Pay\Events\PaymentFlagged;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_transaction_approval_fires_event_and_updates_status(): void
    {
        Event::fake([PaymentCompleted::class]);

        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-TEST1234',
            'merchant_order_id' => 'ORD-101',
            'gateway_key' => 'bkash_personal',
            'amount' => 1500,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        $this->assertTrue($transaction->isPending());

        $transaction->markAsApproved('BL56XYZ789', 95, 'Exact AI Match', true);

        $this->assertTrue($transaction->isPaid());
        $this->assertEquals('ai_approved', $transaction->status);
        $this->assertEquals('BL56XYZ789', $transaction->transaction_id);
        $this->assertEquals(95, $transaction->ai_confidence_score);

        Event::assertDispatched(PaymentCompleted::class);
    }

    public function test_transaction_flagging_fires_event(): void
    {
        Event::fake([PaymentFlagged::class]);

        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-FLAG123',
            'merchant_order_id' => 'ORD-102',
            'gateway_key' => 'nagad_personal',
            'amount' => 2000,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        $transaction->markAsFlagged('Confidence score 65% below threshold');

        $this->assertTrue($transaction->isFlagged());
        $this->assertEquals('flagged', $transaction->status);

        Event::assertDispatched(PaymentFlagged::class);
    }

    public function test_device_hmac_signature_verification(): void
    {
        $device = Device::createWithCredentials('Test Phone Listener');
        $payload = json_encode(['sender' => 'bKash', 'body' => 'Received Tk 500', 'received_at' => now()->toIso8601String()]);

        $validSig = hash_hmac('sha256', $payload, $device->secret_key);
        $invalidSig = 'invalid_signature_hash_value';

        $this->assertTrue($device->verifySignature($payload, $validSig));
        $this->assertFalse($device->verifySignature($payload, $invalidSig));
    }
}
