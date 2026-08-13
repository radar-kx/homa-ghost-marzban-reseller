<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); $table->enum('role', ['admin', 'reseller'])->index(); $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('wallet_balance_irr')->default(0); $table->string('reseller_prefix', 10)->nullable()->unique();
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) { $table->string('email')->primary(); $table->string('token'); $table->timestamp('created_at')->nullable(); });
        Schema::create('sessions', function (Blueprint $table) { $table->string('id')->primary(); $table->foreignId('user_id')->nullable()->index(); $table->string('ip_address', 45)->nullable(); $table->text('user_agent')->nullable(); $table->longText('payload'); $table->integer('last_activity')->index(); });
        Schema::create('cache', function (Blueprint $table) { $table->string('key')->primary(); $table->mediumText('value'); $table->integer('expiration'); });
        Schema::create('cache_locks', function (Blueprint $table) { $table->string('key')->primary(); $table->string('owner'); $table->integer('expiration'); });

        Schema::create('panel_connections', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('base_url'); $table->text('admin_username'); $table->text('admin_password');
            $table->boolean('verify_tls')->default(true); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('plans', function (Blueprint $table) {
            $table->id(); $table->foreignId('panel_connection_id')->constrained()->restrictOnDelete(); $table->string('name');
            $table->decimal('data_limit_gb', 10, 2); $table->unsignedInteger('duration_days'); $table->unsignedBigInteger('price_irr');
            $table->json('proxies'); $table->json('inbounds'); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('service_accounts', function (Blueprint $table) {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('reseller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete(); $table->foreignId('panel_connection_id')->constrained()->restrictOnDelete();
            $table->string('external_username', 32); $table->string('customer_label')->nullable(); $table->enum('status', ['provisioning', 'active', 'failed', 'expired', 'disabled'])->index();
            $table->timestamp('expire_at')->nullable(); $table->unsignedBigInteger('data_limit_bytes'); $table->text('subscription_url')->nullable();
            $table->text('last_error')->nullable(); $table->longText('remote_snapshot')->nullable(); $table->timestamps();
            $table->unique(['panel_connection_id', 'external_username']); $table->index(['reseller_id', 'status']);
        });
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('reseller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_irr'); $table->string('tracking_code', 100)->nullable(); $table->string('receipt_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index(); $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
        });
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id(); $table->enum('owner_type', ['platform', 'reseller']); $table->unsignedBigInteger('owner_id')->default(0);
            $table->string('code', 80); $table->enum('type', ['asset', 'liability', 'revenue', 'expense']); $table->char('currency', 3)->default('IRR');
            $table->timestamps(); $table->unique(['owner_type', 'owner_id', 'code', 'currency'], 'ledger_account_owner_code_unique');
        });
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id(); $table->ulid('public_id')->unique(); $table->string('idempotency_key', 100)->unique();
            $table->string('source_type', 50); $table->string('source_id', 64)->nullable(); $table->string('description'); $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('ledger_transaction_id')->constrained()->restrictOnDelete(); $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->enum('side', ['debit', 'credit']); $table->unsignedBigInteger('amount_irr'); $table->timestamp('created_at')->useCurrent();
            $table->index(['ledger_account_id', 'created_at']);
        });
        Schema::create('wallet_journals', function (Blueprint $table) {
            $table->id(); $table->foreignId('reseller_id')->constrained('users')->restrictOnDelete(); $table->string('idempotency_key', 100)->unique();
            $table->enum('direction', ['credit', 'debit']); $table->unsignedBigInteger('amount_irr'); $table->unsignedBigInteger('balance_after_irr');
            $table->string('source_type', 50); $table->string('source_id', 64)->nullable(); $table->string('description'); $table->text('metadata')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['reseller_id', 'created_at']);
        });
        Schema::create('provision_operations', function (Blueprint $table) {
            $table->id(); $table->ulid('public_id')->unique(); $table->foreignId('reseller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_account_id')->nullable()->constrained()->nullOnDelete(); $table->enum('type', ['create', 'renew']);
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'reconciled', 'unknown'])->index(); $table->unsignedBigInteger('amount_irr');
            $table->longText('request_snapshot')->nullable(); $table->longText('response_snapshot')->nullable(); $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_operations'); Schema::dropIfExists('wallet_journals'); Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions'); Schema::dropIfExists('ledger_accounts'); Schema::dropIfExists('deposit_requests');
        Schema::dropIfExists('service_accounts'); Schema::dropIfExists('plans'); Schema::dropIfExists('panel_connections');
        Schema::dropIfExists('cache_locks'); Schema::dropIfExists('cache'); Schema::dropIfExists('sessions'); Schema::dropIfExists('password_reset_tokens'); Schema::dropIfExists('users');
    }
};
