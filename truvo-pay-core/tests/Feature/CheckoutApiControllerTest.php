<?php

namespace Truvo\Pay\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Truvo\Pay\Models\Merchant;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Tests\TestCase;

class CheckoutApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_can_create_checkout_session_via_api(): void
    {
        $merchant = Merchant::create([
            'name' => 'Acme Test Shop',
            'email' => 'acme@test.local',
            'slug' => 'acme-test',
            'is_active' => true,
        ]);

        $keyData = $merchant->createApiKey('test');

        $response = $this->postJson('/api/v1/checkout/create', [
            'order_id' => 'ORD-999',
            'amount' => 1200.00,
            'currency' => 'BDT',
            'customer_name' => 'Test Customer',
            'customer_phone' => '01700000000',
            'redirect_url' => 'https://acme.shop/success',
        ], [
            'Authorization' => 'Bearer ' . $keyData['api_key'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'truvo_reference',
            'merchant_order_id',
            'amount',
            'currency',
            'status',
            'payment_url',
        ]);

        $this->assertDatabaseHas(config('truvo-pay.table_prefix', 'truvo_') . 'transactions', [
            'merchant_order_id' => 'ORD-999',
            'amount' => 1200.00,
            'status' => 'pending',
        ]);
    }

    public function test_rejects_unauthorized_api_request(): void
    {
        $response = $this->postJson('/api/v1/checkout/create', [
            'order_id' => 'ORD-401',
            'amount' => 500,
        ]);

        $response->assertStatus(401);
    }
}
