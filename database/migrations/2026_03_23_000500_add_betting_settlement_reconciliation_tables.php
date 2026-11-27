<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBettingSettlementReconciliationTables extends Migration
{
    public function up()
    {
        Schema::table('betting_settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('current_source_result_id')->nullable()->after('reviewed_by_cms_user_id');
            $table->string('precedence_state', 40)->default('system_pending')->after('decision_source');
            $table->boolean('manual_override_active')->default(false)->after('precedence_state');
            $table->dateTime('source_reconciled_at')->nullable()->after('reviewed_at');
            $table->string('source_conflict_reason', 191)->nullable()->after('review_notes');

            $table->index(['current_source_result_id'], 'betting_settlements_current_source_result_idx');
            $table->index(['precedence_state'], 'betting_settlements_precedence_state_idx');
        });

        Schema::create('betting_settlement_source_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_settlement_id');
            $table->unsignedBigInteger('replaces_source_result_id')->nullable();
            $table->string('source_adapter_key', 50);
            $table->string('source_result_key', 191)->nullable();
            $table->string('source_event_ref', 191)->nullable();
            $table->string('source_market_ref', 191)->nullable();
            $table->string('source_outcome_ref', 191)->nullable();
            $table->string('candidate_result_code', 30)->default('pending');
            $table->string('candidate_status', 30)->default('review');
            $table->string('precedence_outcome', 40)->default('recorded');
            $table->boolean('is_correction')->default(false);
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->string('source_checksum', 64);
            $table->json('source_payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['betting_settlement_id', 'source_checksum'], 'betting_settlement_source_results_checksum_unique');
            $table->index(['betting_settlement_id', 'source_result_key'], 'betting_settlement_source_results_key_idx');
            $table->index(['betting_settlement_id', 'received_at'], 'betting_settlement_source_results_received_idx');
            $table->index(['replaces_source_result_id'], 'betting_settlement_source_results_replaces_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_settlement_source_results');

        Schema::table('betting_settlements', function (Blueprint $table) {
            $table->dropIndex('betting_settlements_current_source_result_idx');
            $table->dropIndex('betting_settlements_precedence_state_idx');
            $table->dropColumn([
                'current_source_result_id',
                'precedence_state',
                'manual_override_active',
                'source_reconciled_at',
                'source_conflict_reason',
            ]);
        });
    }
}
