<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChessWalletReservationsTable extends Migration
{
    public function up()
    {
        Schema::create('chess_wallet_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('cms_reservation_id')->unique();
            $table->string('cms_placement_ref')->nullable()->index();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->unsignedBigInteger('reserve_transaction_id')->nullable();
            $table->unsignedBigInteger('release_transaction_id')->nullable();
            $table->unsignedBigInteger('settlement_transaction_id')->nullable();
            $table->string('partner_code');
            $table->string('external_user_id');
            $table->string('idempotency_key');
            $table->string('request_id')->nullable();
            $table->string('placement_intent_id');
            $table->string('placed_bet_id')->nullable();
            $table->string('market_instance_id')->nullable();
            $table->string('quote_ref')->nullable();
            $table->dateTime('quote_expires_at')->nullable();
            $table->decimal('stake_amount', 26, 6);
            $table->decimal('payout_amount', 26, 6)->nullable();
            $table->string('currency', 10);
            $table->string('status', 32)->default('created');
            $table->string('reject_reason')->nullable();
            $table->string('release_reason')->nullable();
            $table->string('result_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('reserve_response_payload')->nullable();
            $table->json('settlement_summary')->nullable();
            $table->json('last_settlement_payload')->nullable();
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->dateTime('last_settlement_handoff_at')->nullable();
            $table->timestamps();

            $table->unique(['partner_code', 'external_user_id', 'idempotency_key'], 'chess_wallet_idempotency_unique');
            $table->unique(['partner_code', 'external_user_id', 'placement_intent_id'], 'chess_wallet_intent_unique');

            $table->foreign('provider_id')->references('id')->on('providers');
            $table->foreign('cms_user_id')->references('id')->on('cms_users');
            $table->foreign('reserve_transaction_id')->references('id')->on('cms_user_transactions');
            $table->foreign('release_transaction_id')->references('id')->on('cms_user_transactions');
            $table->foreign('settlement_transaction_id')->references('id')->on('cms_user_transactions');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chess_wallet_reservations');
    }
}
