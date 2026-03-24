<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->char('currency', 3)->default('USD');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status');
            $table->string('transaction_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipper_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('USD');
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailable_class')->nullable();
            $table->string('recipient_email');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('email_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained()->cascadeOnDelete();
            $table->text('exception_message')->nullable();
            $table->text('smtp_response')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('email_attempts');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
