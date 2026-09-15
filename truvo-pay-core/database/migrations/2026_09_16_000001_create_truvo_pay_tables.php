<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('truvo-pay.table_prefix', 'truvo_');

        // 1. Merchants Table
        Schema::create($prefix . 'merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('slug')->unique();
            $table->string('kyc_status')->default('pending'); // pending, approved, rejected
            $table->text('kyc_notes')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 2. Merchant API Keys Table
        Schema::create($prefix . 'merchant_api_keys', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->constrained($prefix . 'merchants')->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('secret_hash');
            $table->enum('mode', ['live', 'test'])->default('test');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 3. Gateway Configs Table
        Schema::create($prefix . 'gateway_configs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained($prefix . 'merchants')->nullOnDelete();
            $table->string('driver_key'); // stripe, bkash_personal, etc.
            $table->string('display_name');
            $table->boolean('is_active')->default(true);
            $table->enum('mode', ['live', 'sandbox'])->default('sandbox');
            $table->integer('priority')->default(0);
            $table->text('credentials')->nullable(); // encrypted JSON vault
            $table->json('supported_currencies')->nullable(); // ['BDT', 'USD']
            $table->json('allowed_countries')->nullable(); // ['BD', 'US']
            $table->text('instructions')->nullable(); // instructions shown at checkout (e.g. personal bKash number)
            $table->timestamps();
        });

        // 4. Devices Table (Companion Android Listener App)
        Schema::create($prefix . 'devices', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained($prefix . 'merchants')->nullOnDelete();
            $table->string('name');
            $table->string('device_id')->unique(); // Hardware / UUID identifier
            $table->string('pairing_token')->unique();
            $table->string('secret_key'); // HMAC signing secret
            $table->timestamp('last_seen_at')->nullable();
            $table->integer('battery_level')->nullable();
            $table->string('network_type')->nullable(); // WiFi, LTE, etc.
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 5. Transactions Table
        Schema::create($prefix . 'transactions', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('truvo_reference')->unique(); // TRUVO-XXXXXXXX
            $table->foreignId('merchant_id')->nullable()->constrained($prefix . 'merchants')->nullOnDelete();
            $table->string('merchant_order_id')->index();
            $table->foreignId('gateway_config_id')->nullable()->constrained($prefix . 'gateway_configs')->nullOnDelete();
            $table->string('gateway_key');
            $table->enum('mode', ['live', 'sandbox', 'test'])->default('sandbox');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('sender_number')->nullable();
            $table->string('transaction_id')->nullable()->index(); // Provider TrxID
            $table->string('status')->default('pending'); // pending, ai_approved, manually_approved, flagged, rejected, refunded, partially_refunded
            $table->integer('ai_confidence_score')->nullable(); // 0 to 100
            $table->text('ai_reasoning')->nullable();
            $table->json('fraud_flags')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('redirect_url')->nullable();
            $table->string('callback_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 6. SMS Logs Table
        Schema::create($prefix . 'sms_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained($prefix . 'devices')->nullOnDelete();
            $table->string('sender_address'); // bKash, 16247, NAGAD
            $table->text('raw_body');
            $table->timestamp('received_at');
            $table->json('extracted_data')->nullable();
            $table->decimal('parsed_amount', 12, 2)->nullable();
            $table->string('parsed_txid')->nullable()->index();
            $table->string('parsed_sender')->nullable();
            $table->string('parsed_gateway')->nullable();
            $table->integer('ai_latency_ms')->nullable();
            $table->boolean('is_matched')->default(false);
            $table->foreignId('matched_transaction_id')->nullable()->constrained($prefix . 'transactions')->nullOnDelete();
            $table->timestamps();
        });

        // 7. Refunds Table
        Schema::create($prefix . 'refunds', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('transaction_id')->constrained($prefix . 'transactions')->cascadeOnDelete();
            $table->string('refund_reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->string('gateway_refund_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // 8. Settlements Table
        Schema::create($prefix . 'settlements', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->constrained($prefix . 'merchants')->cascadeOnDelete();
            $table->string('settlement_reference')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_collected', 14, 2);
            $table->decimal('total_fees', 12, 2);
            $table->decimal('net_payout', 14, 2);
            $table->enum('status', ['pending', 'processing', 'paid'])->default('pending');
            $table->string('payout_method')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 9. Webhook Delivery Logs Table
        Schema::create($prefix . 'webhook_delivery_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained($prefix . 'merchants')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained($prefix . 'transactions')->nullOnDelete();
            $table->string('event_name');
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });

        // 10. Audit Logs Table
        Schema::create($prefix . 'audit_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained($prefix . 'merchants')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // credential_updated, manual_approval, transaction_flagged, etc.
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $prefix = config('truvo-pay.table_prefix', 'truvo_');
        Schema::dropIfExists($prefix . 'audit_logs');
        Schema::dropIfExists($prefix . 'webhook_delivery_logs');
        Schema::dropIfExists($prefix . 'settlements');
        Schema::dropIfExists($prefix . 'refunds');
        Schema::dropIfExists($prefix . 'sms_logs');
        Schema::dropIfExists($prefix . 'transactions');
        Schema::dropIfExists($prefix . 'devices');
        Schema::dropIfExists($prefix . 'gateway_configs');
        Schema::dropIfExists($prefix . 'merchant_api_keys');
        Schema::dropIfExists($prefix . 'merchants');
    }
};
