<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_game_user_risk_limit_overrides')) {
            Schema::create('external_game_user_risk_limit_overrides', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cms_user_id')->index();
                $table->decimal('max_single_bet', 24, 8)->nullable();
                $table->decimal('rolling_24h_stake_limit', 24, 8)->nullable();
                $table->decimal('rolling_24h_net_loss_limit', 24, 8)->nullable();
                $table->unsignedInteger('max_open_external_sessions')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('reason');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->text('revoke_reason')->nullable();
                $table->timestamps();

                $table->index(['cms_user_id', 'is_active', 'expires_at'], 'eg_user_risk_override_active_idx');
            });
        }

        if (!Schema::hasTable('external_game_user_risk_limit_override_audits')) {
            Schema::create('external_game_user_risk_limit_override_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('override_id')->nullable()->index();
                $table->unsignedBigInteger('cms_user_id')->index();
                $table->string('action', 32);
                $table->json('old_limits')->nullable();
                $table->json('new_limits')->nullable();
                $table->text('reason');
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();

                $table->index(['cms_user_id', 'action'], 'eg_user_risk_audit_user_action_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_game_user_risk_limit_override_audits');
        Schema::dropIfExists('external_game_user_risk_limit_overrides');
    }
};
