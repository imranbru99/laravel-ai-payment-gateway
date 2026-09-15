<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('truvo-pay.table_prefix', 'truvo_');

        Schema::create($prefix . 'whatsapp_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained($prefix . 'transactions')->nullOnDelete();
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->string('recipient_phone'); // E.164 phone number
            $table->string('provider'); // meta, twilio, ultramsg, fake
            $table->string('message_type')->default('text'); // text, receipt, payment_link, alert
            $table->text('content');
            $table->string('message_id')->nullable()->index(); // provider msg id
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('truvo-pay.table_prefix', 'truvo_');
        Schema::dropIfExists($prefix . 'whatsapp_logs');
    }
};
