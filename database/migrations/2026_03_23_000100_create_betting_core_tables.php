<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBettingCoreTables extends Migration
{
    public function up()
    {
        Schema::create('betting_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('feed_key', 100)->unique();
            $table->string('display_name', 120);
            $table->string('adapter_key', 100);
            $table->string('mode', 30)->default('prematch');
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('supports_prematch')->default(true);
            $table->json('settings')->nullable();
            $table->dateTime('last_pulled_at')->nullable();
            $table->dateTime('last_ingested_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        Schema::create('betting_raw_feeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_feed_id');
            $table->string('external_batch_ref', 120)->nullable();
            $table->string('ingest_status', 30)->default('pending');
            $table->dateTime('pulled_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedInteger('mapped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('payload')->nullable();
            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['betting_feed_id', 'ingest_status'], 'betting_raw_feeds_feed_status_idx');
            $table->index(['pulled_at']);
        });

        Schema::create('betting_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->string('sport_code', 40);
            $table->string('lifecycle_status', 30)->default('draft');
            $table->string('trading_status', 30)->default('open');
            $table->string('visibility_status', 30)->default('visible');
            $table->string('canonical_name', 180);
            $table->string('tournament_name', 180)->nullable();
            $table->string('participant_home', 120);
            $table->string('participant_away', 120);
            $table->dateTime('starts_at');
            $table->dateTime('discovered_at')->nullable();
            $table->dateTime('last_source_seen_at')->nullable();
            $table->unsignedBigInteger('current_quote_version_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sport_code', 'starts_at'], 'betting_events_sport_start_idx');
            $table->index(['lifecycle_status', 'trading_status'], 'betting_events_status_idx');
        });

        Schema::create('betting_markets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_event_id');
            $table->string('market_type', 50);
            $table->string('specifier_key', 80)->default('default');
            $table->decimal('line', 10, 3)->nullable();
            $table->string('line_unit', 20)->nullable();
            $table->string('status', 30)->default('open');
            $table->string('suspension_reason', 180)->nullable();
            $table->string('display_name', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['betting_event_id', 'market_type', 'specifier_key'], 'betting_market_identity_unique');
            $table->index(['status', 'sort_order'], 'betting_market_status_sort_idx');
        });

        Schema::create('betting_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_market_id');
            $table->string('outcome_code', 40);
            $table->string('display_name', 120);
            $table->string('status', 30)->default('open');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['betting_market_id', 'outcome_code'], 'betting_outcome_market_code_unique');
            $table->index(['status', 'sort_order'], 'betting_outcome_status_sort_idx');
        });

        Schema::create('betting_source_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_feed_id');
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('source_context', 191)->nullable();
            $table->string('source_key', 191);
            $table->string('source_label', 191)->nullable();
            $table->string('source_group', 80)->nullable();
            $table->dateTime('mapped_at')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['betting_feed_id', 'entity_type', 'source_context', 'source_key'], 'betting_source_mapping_unique');
            $table->index(['entity_type', 'entity_id'], 'betting_source_mapping_entity_idx');
        });

        Schema::create('betting_canonical_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_feed_id');
            $table->unsignedBigInteger('betting_raw_feed_id');
            $table->unsignedBigInteger('betting_event_id')->nullable();
            $table->string('source_event_key', 191)->nullable();
            $table->string('source_sport_key', 80)->nullable();
            $table->string('mapping_status', 30)->default('pending');
            $table->unsignedInteger('supported_market_count')->default(0);
            $table->unsignedInteger('unsupported_market_count')->default(0);
            $table->unsignedInteger('supported_outcome_count')->default(0);
            $table->unsignedInteger('unsupported_outcome_count')->default(0);
            $table->json('canonical_payload')->nullable();
            $table->json('raw_snapshot')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('mapped_at')->nullable();
            $table->timestamps();

            $table->unique(['betting_raw_feed_id', 'source_event_key'], 'betting_canonical_result_unique');
            $table->index(['mapping_status', 'mapped_at'], 'betting_canonical_result_status_idx');
        });

        Schema::create('betting_quote_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_event_id');
            $table->unsignedBigInteger('betting_feed_id')->nullable();
            $table->unsignedBigInteger('origin_raw_feed_id')->nullable();
            $table->uuid('version_uuid')->unique();
            $table->unsignedInteger('version_no')->default(1);
            $table->string('quote_status', 30)->default('draft');
            $table->unsignedInteger('selection_count')->default(0);
            $table->dateTime('compiled_at')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->string('trader_note', 191)->nullable();
            $table->string('payload_digest', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['betting_event_id', 'version_no'], 'betting_event_version_unique');
            $table->index(['quote_status', 'effective_from'], 'betting_quote_versions_status_idx');
        });

        Schema::create('betting_quote_selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('betting_quote_version_id');
            $table->unsignedBigInteger('betting_market_id');
            $table->unsignedBigInteger('betting_outcome_id');
            $table->string('offer_status', 30)->default('open');
            $table->decimal('decimal_price', 12, 6);
            $table->decimal('implied_probability', 10, 6)->nullable();
            $table->string('trading_notes', 180)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['betting_quote_version_id', 'betting_outcome_id'], 'betting_quote_selection_unique');
            $table->index(['betting_market_id', 'offer_status'], 'betting_quote_selection_market_idx');
        });

        Schema::create('betting_acceptances', function (Blueprint $table) {
            $table->id();
            $table->uuid('acceptance_ref')->unique();
            $table->unsignedBigInteger('betting_quote_version_id')->nullable();
            $table->unsignedBigInteger('betting_quote_selection_id')->nullable();
            $table->unsignedBigInteger('betting_event_id')->nullable();
            $table->unsignedBigInteger('betting_market_id')->nullable();
            $table->unsignedBigInteger('betting_outcome_id')->nullable();
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->unsignedBigInteger('root_user_id')->nullable();
            $table->string('currency', 5)->default('TRY');
            $table->decimal('stake_amount', 16, 6);
            $table->decimal('accepted_price', 12, 6);
            $table->string('status', 30)->default('pending');
            $table->string('decision', 30)->default('pending');
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->string('rejected_reason', 191)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['betting_event_id', 'status'], 'betting_acceptances_event_status_idx');
            $table->index(['cms_user_id', 'requested_at'], 'betting_acceptances_user_req_idx');
        });

        Schema::create('betting_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('settlement_ref')->unique();
            $table->unsignedBigInteger('betting_acceptance_id')->unique();
            $table->string('status', 30)->default('pending');
            $table->string('result_code', 30)->default('pending');
            $table->decimal('return_amount', 16, 6)->default(0);
            $table->decimal('profit_amount', 16, 6)->default(0);
            $table->dateTime('settled_at')->nullable();
            $table->string('reason', 191)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['status', 'result_code'], 'betting_settlements_status_result_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_settlements');
        Schema::dropIfExists('betting_acceptances');
        Schema::dropIfExists('betting_quote_selections');
        Schema::dropIfExists('betting_quote_versions');
        Schema::dropIfExists('betting_canonical_results');
        Schema::dropIfExists('betting_source_mappings');
        Schema::dropIfExists('betting_outcomes');
        Schema::dropIfExists('betting_markets');
        Schema::dropIfExists('betting_events');
        Schema::dropIfExists('betting_raw_feeds');
        Schema::dropIfExists('betting_feeds');
    }
}
