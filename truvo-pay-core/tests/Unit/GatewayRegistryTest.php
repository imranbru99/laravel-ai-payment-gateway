<?php

namespace Truvo\Pay\Tests\Unit;

use Truvo\Pay\Drivers\FakeSandboxDriver;
use Truvo\Pay\Drivers\StripeDriver;
use Truvo\Pay\Registry\GatewayRegistry;
use Truvo\Pay\Tests\TestCase;

class GatewayRegistryTest extends TestCase
{
    public function test_can_register_and_retrieve_drivers(): void
    {
        $registry = new GatewayRegistry();
        $registry->register('fake_sandbox', FakeSandboxDriver::class);
        $registry->register('stripe', StripeDriver::class);

        $this->assertTrue($registry->has('fake_sandbox'));
        $this->assertTrue($registry->has('stripe'));
        $this->assertInstanceOf(FakeSandboxDriver::class, $registry->get('fake_sandbox'));
        $this->assertInstanceOf(StripeDriver::class, $registry->get('stripe'));
    }
}
