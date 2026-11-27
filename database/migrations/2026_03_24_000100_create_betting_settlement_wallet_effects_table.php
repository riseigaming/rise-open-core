<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBettingSettlementWalletEffectsTable extends Migration
{
    public function up()
    {
        Schema::create('betting_settlement_wallet_effects', function (Blueprint $table) {
            $table->id();
            $table->uuid('effect_ref')->unique();
            $table->unsignedBigInteger('betting_settlement_id');
            $table->unsignedBigInteger('betting_settlement_source_result_id')->nullable();
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->unsignedBigInteger('cms_user_transaction_id')->nullable();
            $table->unsignedBigInteger('reversed_wallet_effect_id')->nullable();
            $table->unsignedBigInteger('reversed_by_wallet_effect_id')->nullable();
            $table->string('effect_phase', 20)->default('apply');
            $table->string('effect_family', 30);
            $table->string('result_code', 30)->nullable();
            $table->string('direction', 10)->default('credit');
            $table->decimal('amount', 16, 6)->default(0);
            $table->string('currency', 5)->default('TRY');
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['betting_settlement_id', 'effect_phase'], 'betting_settlement_wallet_effects_settlement_phase_idx');
            $table->index(['betting_settlement_source_result_id'], 'betting_settlement_wallet_effects_source_result_idx');
            $table->index(['cms_user_transaction_id'], 'betting_settlement_wallet_effects_transaction_idx');
            $table->index(['reversed_wallet_effect_id'], 'betting_settlement_wallet_effects_reversed_idx');
            $table->index(['reversed_by_wallet_effect_id'], 'betting_settlement_wallet_effects_reversed_by_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_settlement_wallet_effects');
    }
}
