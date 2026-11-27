<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_manual_overrides')) {
            return;
        }

        Schema::create('finance_manual_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_id', 120)->unique();
            $table->unsignedBigInteger('actor_admin_id');
            $table->unsignedBigInteger('cms_user_id');
            $table->unsignedBigInteger('cms_user_transaction_id')->nullable();
            $table->string('transaction_reference', 120)->nullable();
            $table->smallInteger('before_status')->nullable();
            $table->smallInteger('after_status')->nullable();
            $table->string('external_resolution', 48);
            $table->string('economic_action', 48);
            $table->unsignedBigInteger('wallet_account_id')->nullable();
            $table->string('wallet_type', 40)->nullable();
            $table->string('asset', 16)->nullable();
            $table->decimal('amount', 24, 8)->nullable();
            $table->decimal('balance_before', 24, 8)->nullable();
            $table->decimal('balance_after', 24, 8)->nullable();
            $table->text('reason');
            $table->text('evidence_reference')->nullable();
            $table->boolean('manual_override')->default(true);
            $table->char('request_fingerprint', 64);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cms_user_id', 'created_at'], 'finance_manual_overrides_user_created_idx');
            $table->index(['cms_user_transaction_id', 'created_at'], 'finance_manual_overrides_transaction_created_idx');
            $table->index(['actor_admin_id', 'created_at'], 'finance_manual_overrides_actor_created_idx');
        });
    }

    public function down(): void
    {
        // Immutable financial audit history survives routine application rollbacks.
        // Disable/revert the feature code instead; deletion requires a separate,
        // explicitly approved retention procedure.
    }
};

