<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('external_game_real_money_eligibility_overrides')) {
            Schema::create('external_game_real_money_eligibility_overrides', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cms_user_id');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('reason');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('revoked_by')->nullable();
                $table->text('revoke_reason')->nullable();
                $table->timestamps();

                $table->index('cms_user_id', 'eg_rm_override_user_idx');
                $table->index('is_active', 'eg_rm_override_active_flag_idx');
                $table->index(['cms_user_id', 'is_active', 'expires_at'], 'eg_real_money_override_active_idx');
            });
        }

        if (!Schema::hasTable('external_game_real_money_eligibility_override_audits')) {
            Schema::create('external_game_real_money_eligibility_override_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('override_id')->nullable();
                $table->unsignedBigInteger('cms_user_id');
                $table->string('action', 32);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason');
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();

                $table->index('override_id', 'eg_rm_audit_override_idx');
                $table->index('cms_user_id', 'eg_rm_audit_user_idx');
                $table->index(['cms_user_id', 'action'], 'eg_real_money_audit_user_action_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_game_real_money_eligibility_override_audits');
        Schema::dropIfExists('external_game_real_money_eligibility_overrides');
    }
};
