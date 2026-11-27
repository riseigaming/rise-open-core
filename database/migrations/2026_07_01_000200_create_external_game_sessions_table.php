<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_game_sessions')) {
            return;
        }

        Schema::create('external_game_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_alias', 96);
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('cms_game_id');
            $table->string('provider_game_id', 191);
            $table->unsignedBigInteger('cms_user_id');
            $table->unsignedBigInteger('root_user_id')->nullable();
            $table->unsignedBigInteger('wallet_account_id')->nullable();
            $table->string('wallet_account_type', 64)->nullable();
            $table->string('wallet_account_key', 64)->nullable();
            $table->string('wallet_currency', 8);
            $table->string('mode', 16)->default('real');
            $table->string('session_token', 96)->unique();
            $table->json('playability_snapshot')->nullable();
            $table->string('launch_payload_digest', 64)->nullable();
            $table->json('launch_payload')->nullable();
            $table->text('provider_launch_url')->nullable();
            $table->string('provider_launch_reference', 191)->nullable();
            $table->json('provider_response')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['provider_alias', 'provider_game_id'], 'external_game_sessions_provider_game_idx');
            $table->index(['cms_user_id', 'status'], 'external_game_sessions_user_status_idx');
            $table->index(['wallet_account_id'], 'external_game_sessions_wallet_idx');
            $table->index(['launched_at'], 'external_game_sessions_launched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_game_sessions');
    }
};
