<?php

namespace Truvo\Pay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Drivers\BkashPersonalDriver;
use Truvo\Pay\Drivers\FakeSandboxDriver;

class DriversTest extends TestCase
{
    public function test_fake_sandbox_driver_auto_approves(): void
    {
        $driver = new FakeSandboxDriver();
        $driver->setCredentials(['simulator_mode' => 'auto_approve']);

        $request = PaymentRequest::fromArray([
            'order_id' => 'TEST-101',
            'amount' => 500,
            'currency' => 'BDT',
        ]);

        $response = $driver->charge($request);

        $this->assertTrue($response->success);
        $this->assertEquals('paid', $response->status);
        $this->assertNotEmpty($response->transactionId);
    }

    public function test_bkash_personal_driver_is_manual(): void
    {
        $driver = new BkashPersonalDriver();
        $driver->setCredentials(['personal_number' => '01700000000', 'account_type' => 'personal']);

        $this->assertTrue($driver->isManual());

        $request = PaymentRequest::fromArray([
            'order_id' => 'TEST-102',
            'amount' => 1250,
            'currency' => 'BDT',
        ]);

        $response = $driver->charge($request);

        $this->assertTrue($response->success);
        $this->assertEquals('pending', $response->status);
        $this->assertStringContainsString('01700000000', $response->instructions);
        $this->assertStringContainsString('1,250.00', $response->instructions);
    }
}
