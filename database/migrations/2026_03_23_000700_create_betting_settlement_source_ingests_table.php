<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBettingSettlementSourceIngestsTable extends Migration
{
    public function up()
    {
        Schema::create('betting_settlement_source_ingests', function (Blueprint $table) {
            $table->id();
            $table->uuid('ingest_ref')->unique();
            $table->string('adapter_key', 50);
            $table->string('ingest_origin', 30)->default('provider_ingest');
            $table->string('payload_checksum', 64)->unique();
            $table->string('ingest_status', 30)->default('unresolved');
            $table->string('last_action', 30)->default('ingested');
            $table->string('matching_confidence', 30)->default('none');
            $table->string('resolution_reason', 191)->nullable();
            $table->string('resolution_source', 30)->default('automatic');
            $table->string('source_result_key', 191)->nullable();
            $table->string('source_event_ref', 191)->nullable();
            $table->string('source_market_ref', 191)->nullable();
            $table->string('source_outcome_ref', 191)->nullable();
            $table->string('input_settlement_ref', 191)->nullable();
            $table->string('input_acceptance_ref', 191)->nullable();
            $table->string('bound_settlement_ref', 191)->nullable();
            $table->string('bound_acceptance_ref', 191)->nullable();
            $table->unsignedBigInteger('betting_settlement_id')->nullable();
            $table->unsignedBigInteger('betting_acceptance_id')->nullable();
            $table->unsignedBigInteger('betting_settlement_source_result_id')->nullable();
            $table->string('normalization_status', 30)->default('normalized');
            $table->string('normalization_reason', 191)->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('replay_count')->default(0);
            $table->dateTime('first_received_at')->nullable();
            $table->dateTime('last_received_at')->nullable();
            $table->dateTime('last_retried_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('ignored_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ingest_status', 'last_received_at'], 'betting_settlement_source_ingests_status_received_idx');
            $table->index(['adapter_key', 'source_result_key'], 'betting_settlement_source_ingests_adapter_result_idx');
            $table->index(['betting_settlement_id'], 'betting_settlement_source_ingests_settlement_idx');
            $table->index(['betting_acceptance_id'], 'betting_settlement_source_ingests_acceptance_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_settlement_source_ingests');
    }
}
