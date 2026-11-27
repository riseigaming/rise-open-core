<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallet_accounts')) {
            Schema::create('wallet_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cms_user_id');
                $table->string('wallet_type', 40);
                $table->string('wallet_key', 40)->default('default');
                $table->string('asset', 16);
                $table->string('display_name', 120)->nullable();
                $table->decimal('available_balance', 24, 8)->default(0);
                $table->decimal('pending_balance', 24, 8)->default(0);
                $table->string('status', 20)->default('active');
                $table->dateTime('last_synced_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['cms_user_id', 'wallet_type', 'wallet_key', 'asset'], 'wallet_accounts_owner_type_key_asset_uidx');
                $table->index(['cms_user_id', 'wallet_type'], 'wallet_accounts_owner_type_idx');
                $table->index(['asset', 'status'], 'wallet_accounts_asset_status_idx');
            });
        }

        if (!Schema::hasTable('wallet_ledger_entries')) {
            Schema::create('wallet_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wallet_account_id');
                $table->unsignedBigInteger('cms_user_id');
                $table->string('entry_type', 60);
                $table->string('direction', 12)->default('credit');
                $table->string('status', 20)->default('applied');
                $table->decimal('amount', 24, 8)->default(0);
                $table->decimal('balance_before', 24, 8)->default(0);
                $table->decimal('balance_after', 24, 8)->default(0);
                $table->string('asset', 16);
                $table->string('source_type', 60)->nullable();
                $table->string('source_reference', 120)->nullable();
                $table->string('idempotency_key', 120);
                $table->dateTime('applied_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['wallet_account_id', 'idempotency_key'], 'wallet_ledger_entries_account_idempotency_uidx');
                $table->index(['cms_user_id', 'entry_type'], 'wallet_ledger_entries_owner_type_idx');
                $table->index(['source_type', 'source_reference'], 'wallet_ledger_entries_source_idx');
            });
        }

        if (!Schema::hasTable('wallet_conversion_requests')) {
            Schema::create('wallet_conversion_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cms_user_id');
                $table->unsignedBigInteger('source_wallet_account_id');
                $table->unsignedBigInteger('target_wallet_account_id')->nullable();
                $table->string('source_asset', 16);
                $table->string('target_asset', 16);
                $table->decimal('requested_amount', 24, 8)->default(0);
                $table->decimal('quoted_rate', 24, 8)->nullable();
                $table->decimal('fee_percentage', 10, 4)->default(0);
                $table->decimal('fee_amount', 24, 8)->default(0);
                $table->decimal('receive_amount', 24, 8)->default(0);
                $table->dateTime('quote_expires_at')->nullable();
                $table->string('status', 20)->default('requested');
                $table->string('idempotency_key', 120)->unique();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['cms_user_id', 'status'], 'wallet_conversion_requests_owner_status_idx');
            });
        }

        if (!Schema::hasTable('wallet_transfer_requests')) {
            Schema::create('wallet_transfer_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cms_user_id');
                $table->unsignedBigInteger('source_wallet_account_id');
                $table->unsignedBigInteger('target_wallet_account_id');
                $table->string('asset', 16);
                $table->decimal('requested_amount', 24, 8)->default(0);
                $table->string('status', 20)->default('requested');
                $table->string('idempotency_key', 120)->unique();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['cms_user_id', 'status'], 'wallet_transfer_requests_owner_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfer_requests');
        Schema::dropIfExists('wallet_conversion_requests');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_accounts');
    }
};

