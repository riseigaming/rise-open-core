<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendBettingSettlementSourceResultsForIngestBoundary extends Migration
{
    public function up()
    {
        Schema::table('betting_settlement_source_results', function (Blueprint $table) {
            $table->string('ingest_origin', 30)->default('provider_ingest')->after('source_adapter_key');
            $table->string('normalization_status', 30)->default('normalized')->after('candidate_status');
            $table->string('normalization_reason', 191)->nullable()->after('normalization_status');

            $table->index(['ingest_origin'], 'betting_settlement_source_results_origin_idx');
            $table->index(['normalization_status'], 'betting_settlement_source_results_normalization_idx');
        });
    }

    public function down()
    {
        Schema::table('betting_settlement_source_results', function (Blueprint $table) {
            $table->dropIndex('betting_settlement_source_results_origin_idx');
            $table->dropIndex('betting_settlement_source_results_normalization_idx');
            $table->dropColumn([
                'ingest_origin',
                'normalization_status',
                'normalization_reason',
            ]);
        });
    }
}
