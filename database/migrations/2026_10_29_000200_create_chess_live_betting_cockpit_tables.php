<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChessLiveBettingCockpitTables extends Migration
{
    public function up()
    {
        Schema::create('chess_live_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_event_key', 120)->unique();
            $table->string('title', 191);
            $table->string('stage_name', 120)->nullable();
            $table->string('round_name', 120)->nullable();
            $table->string('official_status', 60)->default('scheduled');
            $table->string('official_result', 60)->nullable();
            $table->string('official_source_url', 255)->nullable();
            $table->json('standings_snapshot')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['official_status', 'source_updated_at'], 'chess_live_events_status_updated_idx');
        });

        Schema::create('chess_live_boards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chess_live_event_id');
            $table->string('external_board_key', 120)->unique();
            $table->unsignedInteger('board_no');
            $table->string('white_player_name', 120);
            $table->string('black_player_name', 120);
            $table->unsignedSmallInteger('white_rating')->nullable();
            $table->unsignedSmallInteger('black_rating')->nullable();
            $table->string('white_points', 20)->nullable();
            $table->string('black_points', 20)->nullable();
            $table->unsignedInteger('white_clock_seconds')->nullable();
            $table->unsignedInteger('black_clock_seconds')->nullable();
            $table->string('official_status', 60)->default('scheduled');
            $table->string('official_result', 60)->nullable();
            $table->string('official_source_url', 255)->nullable();
            $table->boolean('featured_flag')->default(false);
            $table->dateTime('source_updated_at')->nullable();
            $table->string('source_ref', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['chess_live_event_id', 'board_no'], 'chess_live_boards_event_board_idx');
            $table->index(['featured_flag', 'official_status'], 'chess_live_boards_featured_status_idx');
        });

        Schema::create('chess_betting_markets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chess_live_event_id');
            $table->unsignedBigInteger('chess_live_board_id')->nullable();
            $table->string('scope_type', 40);
            $table->string('template_type', 60);
            $table->string('template_variant', 60)->default('default');
            $table->string('title', 191);
            $table->string('status', 40)->default('draft');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('settle_reason', 60)->nullable();
            $table->text('settle_note')->nullable();
            $table->text('operator_note')->nullable();
            $table->string('result_selection_code', 60)->nullable();
            $table->json('result_payload')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('source_ref', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['chess_live_event_id', 'status'], 'chess_betting_markets_event_status_idx');
            $table->index(['chess_live_board_id', 'template_type'], 'chess_betting_markets_board_template_idx');
            $table->index(['template_type', 'template_variant'], 'chess_betting_markets_template_variant_idx');
        });

        Schema::create('chess_betting_market_selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chess_betting_market_id');
            $table->string('selection_code', 60);
            $table->string('label', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['chess_betting_market_id', 'selection_code'], 'chess_betting_market_selection_unique');
        });

        Schema::create('chess_betting_market_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chess_betting_market_id');
            $table->unsignedBigInteger('chess_live_event_id');
            $table->unsignedBigInteger('chess_live_board_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action_type', 60);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40)->nullable();
            $table->string('reason_type', 60)->nullable();
            $table->text('note')->nullable();
            $table->string('source_ref', 191)->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('created_at');

            $table->index(['chess_live_event_id', 'created_at'], 'chess_betting_market_action_logs_event_created_idx');
            $table->index(['chess_live_board_id', 'created_at'], 'chess_betting_market_action_logs_board_created_idx');
            $table->index(['chess_betting_market_id', 'created_at'], 'chess_betting_market_action_logs_market_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chess_betting_market_action_logs');
        Schema::dropIfExists('chess_betting_market_selections');
        Schema::dropIfExists('chess_betting_markets');
        Schema::dropIfExists('chess_live_boards');
        Schema::dropIfExists('chess_live_events');
    }
}
