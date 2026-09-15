<?php

namespace Truvo\Pay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Credentials\CredentialVault;

class CredentialVaultTest extends TestCase
{
    public function test_masking_replaces_secrets_with_bullet_points(): void
    {
        $credentials = [
            'publishable_key' => 'pk_live_1234567890abcdef',
            'secret_key' => 'sk_live_9876543210fedcba',
            'store_id' => 'my_store_1',
        ];

        $fields = [
            CredentialField::make('publishable_key', 'Pub Key')->secret(false),
            CredentialField::make('secret_key', 'Secret Key')->secret(true),
            CredentialField::make('store_id', 'Store ID')->secret(false),
        ];

        $masked = CredentialVault::mask($credentials, $fields);

        $this->assertStringContainsString('••••', $masked['secret_key']);
        $this->assertEquals('my_store_1', $masked['store_id']);
    }

    public function test_merging_preserves_existing_secrets_if_masked(): void
    {
        $existing = [
            'secret_key' => 'sk_live_super_secret_token_12345',
            'username' => 'merchant_user',
        ];

        $submitted = [
            'secret_key' => 'sk_l••••2345', // user left masked placeholder unchanged
            'username' => 'merchant_user_updated',
        ];

        $merged = CredentialVault::mergeWithExisting($submitted, $existing);

        $this->assertEquals('sk_live_super_secret_token_12345', $merged['secret_key']);
        $this->assertEquals('merchant_user_updated', $merged['username']);
    }
}
