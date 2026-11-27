<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrontBettingEnabledToBettingEventsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('betting_events') || Schema::hasColumn('betting_events', 'front_betting_enabled')) {
            return;
        }

        Schema::table('betting_events', function (Blueprint $table) {
            $table->boolean('front_betting_enabled')->default(true)->after('visibility_status');
            $table->index(['front_betting_enabled', 'starts_at'], 'betting_events_front_betting_idx');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('betting_events') || !Schema::hasColumn('betting_events', 'front_betting_enabled')) {
            return;
        }

        Schema::table('betting_events', function (Blueprint $table) {
            $table->dropIndex('betting_events_front_betting_idx');
            $table->dropColumn('front_betting_enabled');
        });
    }
}
