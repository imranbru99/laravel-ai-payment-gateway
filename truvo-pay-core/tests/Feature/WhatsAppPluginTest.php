<?php

namespace Truvo\Pay\Tests\Feature;

use Illuminate\Http\Request;
use Truvo\Pay\Events\DeviceWentOffline;
use Truvo\Pay\Events\PaymentCompleted;
use Truvo\Pay\Events\PaymentFlagged;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Tests\TestCase;
use Truvo\Pay\WhatsApp\Models\WhatsAppLog;
use Truvo\Pay\WhatsApp\WhatsAppService;

class WhatsAppPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        config()->set('truvo-pay.whatsapp.enabled', true);
        config()->set('truvo-pay.whatsapp.default_driver', 'fake');
        config()->set('truvo-pay.whatsapp.drivers.meta.verify_token', 'test_verify_token_123');
        config()->set('truvo-pay.whatsapp.merchant_alert_phone', '+8801999888777');
    }

    public function test_whatsapp_service_dispatches_text_message_and_logs(): void
    {
        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $result = $service->driver()->sendTextMessage('+8801700000001', 'Hello from Truvo Pay!');

        $this->assertTrue($result);
        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'recipient_phone' => '+8801700000001',
            'direction' => 'outbound',
            'message_type' => 'text',
            'status' => 'sent',
            'provider' => 'fake',
        ]);
    }

    public function test_whatsapp_service_sends_receipt_for_transaction(): void
    {
        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-WA-REC01',
            'merchant_order_id' => 'ORD-WA-01',
            'gateway_key' => 'bkash_merchant',
            'amount' => 2500,
            'currency' => 'BDT',
            'customer_phone' => '+8801800000002',
            'customer_name' => 'Karim Rahman',
            'status' => 'paid',
            'transaction_id' => 'TRXWA999888',
        ]);

        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);
        $sent = $service->sendReceipt($transaction);

        $this->assertTrue($sent);
        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'transaction_id' => $transaction->id,
            'recipient_phone' => '+8801800000002',
            'direction' => 'outbound',
            'message_type' => 'receipt',
            'status' => 'sent',
        ]);
    }

    public function test_payment_completed_event_triggers_whatsapp_receipt_listener(): void
    {
        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-EVENT-WA',
            'merchant_order_id' => 'ORD-EVENT-WA',
            'gateway_key' => 'nagad_personal',
            'amount' => 1200,
            'currency' => 'BDT',
            'customer_phone' => '+8801600000003',
            'customer_name' => 'Fatima Begum',
            'status' => 'pending',
        ]);

        // Simulating approval which fires PaymentCompleted event
        $transaction->markAsApproved('NGDWA776655', 98, 'AI Exact Match', true);

        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'transaction_id' => $transaction->id,
            'recipient_phone' => '+8801600000003',
            'direction' => 'outbound',
            'message_type' => 'receipt',
        ]);
    }

    public function test_meta_webhook_challenge_verification_endpoint(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token_123',
            'hub_challenge' => 'CHALLENGE_STRING_7788',
        ]));

        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_STRING_7788', $response->getContent());
    }

    public function test_inbound_whatsapp_order_status_query(): void
    {
        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-BOT-INQUIRY',
            'merchant_order_id' => 'ORD-BOT-4455',
            'gateway_key' => 'sslcommerz',
            'amount' => 3400,
            'currency' => 'BDT',
            'status' => 'paid',
            'transaction_id' => 'SSLWA112233',
        ]);

        /** @var WhatsAppService $service */
        $service = app(WhatsAppService::class);

        $request = Request::create('/api/v1/whatsapp/webhook', 'POST', [
            'From' => '+8801711223344',
            'Body' => 'What is the status of ORD-BOT-4455?',
            'MessageSid' => 'FAKE-MSG-101',
        ]);

        $result = $service->handleInbound($request);

        $this->assertEquals('replied_order_status', $result['status']);
        $this->assertEquals('ORD-BOT-4455', $result['order_id']);

        // Assert inbound message was logged
        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'recipient_phone' => '+8801711223344',
            'direction' => 'inbound',
            'status' => 'delivered',
        ]);
    }

    public function test_security_alert_dispatched_on_device_went_offline_event(): void
    {
        $device = Device::createWithCredentials('Warehouse Phone 1');
        $device->update(['last_seen_at' => now()->subMinutes(15)]);

        event(new DeviceWentOffline($device));

        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'recipient_phone' => '+8801999888777',
            'direction' => 'outbound',
            'message_type' => 'alert',
            'status' => 'sent',
        ]);
    }

    public function test_security_alert_dispatched_on_payment_flagged_event(): void
    {
        $transaction = Transaction::create([
            'truvo_reference' => 'TRUVO-SUSPICIOUS-01',
            'merchant_order_id' => 'ORD-FLAG-01',
            'gateway_key' => 'bkash_personal',
            'amount' => 50000,
            'currency' => 'BDT',
            'status' => 'pending',
        ]);

        event(new PaymentFlagged($transaction, 'Velocity spike: 5 attempts in 1 minute'));

        $this->assertDatabaseHas('truvo_whatsapp_logs', [
            'recipient_phone' => '+8801999888777',
            'direction' => 'outbound',
            'message_type' => 'alert',
            'status' => 'sent',
        ]);
    }
}
