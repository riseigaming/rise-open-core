<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendBettingQuoteTablesForInternalVersioning extends Migration
{
    public function up()
    {
        Schema::table('betting_quote_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('betting_canonical_result_id')->nullable()->after('betting_feed_id');
            $table->unsignedBigInteger('replaces_quote_version_id')->nullable()->after('betting_canonical_result_id');
            $table->string('generation_mode', 30)->default('automated')->after('quote_status');
            $table->string('trading_state', 30)->default('open')->after('generation_mode');
            $table->string('manual_action', 40)->nullable()->after('trading_state');
            $table->string('manual_reason', 191)->nullable()->after('manual_action');
            $table->unsignedBigInteger('published_by_cms_user_id')->nullable()->after('manual_reason');
            $table->dateTime('published_at')->nullable()->after('published_by_cms_user_id');
            $table->json('override_meta')->nullable()->after('meta');

            $table->index(['betting_canonical_result_id'], 'betting_quote_versions_canonical_result_idx');
            $table->index(['generation_mode', 'trading_state'], 'betting_quote_versions_generation_state_idx');
        });

        Schema::table('betting_quote_selections', function (Blueprint $table) {
            $table->decimal('base_decimal_price', 12, 6)->nullable()->after('decimal_price');
            $table->decimal('margin_factor', 10, 6)->nullable()->after('base_decimal_price');
            $table->string('trading_decision_source', 30)->default('automated')->after('offer_status');
            $table->json('override_meta')->nullable()->after('meta');

            $table->index(['offer_status', 'trading_decision_source'], 'betting_quote_selection_status_source_idx');
        });
    }

    public function down()
    {
        Schema::table('betting_quote_selections', function (Blueprint $table) {
            $table->dropIndex('betting_quote_selection_status_source_idx');
            $table->dropColumn([
                'base_decimal_price',
                'margin_factor',
                'trading_decision_source',
                'override_meta',
            ]);
        });

        Schema::table('betting_quote_versions', function (Blueprint $table) {
            $table->dropIndex('betting_quote_versions_canonical_result_idx');
            $table->dropIndex('betting_quote_versions_generation_state_idx');
            $table->dropColumn([
                'betting_canonical_result_id',
                'replaces_quote_version_id',
                'generation_mode',
                'trading_state',
                'manual_action',
                'manual_reason',
                'published_by_cms_user_id',
                'published_at',
                'override_meta',
            ]);
        });
    }
}
