<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internal_game_sessions',function(Blueprint $t) {
            $t->id(); $t->string('public_id',64)->unique(); $t->uuid('session_token')->unique();
            $t->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $t->foreignId('cms_game_id')->constrained('cms_games')->restrictOnDelete();
            $t->unsignedBigInteger('cms_user_id')->nullable(); $t->unsignedBigInteger('root_user_id')->nullable();
            $t->string('game_code',100); $t->string('currency',5)->default('VC');
            $t->string('status',40)->default('active'); $t->boolean('is_demo')->default(true);
            $t->json('launch_payload'); $t->dateTime('launched_at'); $t->dateTime('last_activity_at');
            $t->dateTime('expires_at'); $t->dateTime('ended_at')->nullable(); $t->timestamps();
            $t->index(['cms_game_id','status']);
        });
        Schema::create('internal_game_rounds',function(Blueprint $t) {
            $t->id(); $t->foreignId('internal_game_session_id')->constrained('internal_game_sessions')->restrictOnDelete();
            $t->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $t->foreignId('cms_game_id')->constrained('cms_games')->restrictOnDelete();
            $t->unsignedBigInteger('cms_user_id')->nullable(); $t->unsignedBigInteger('root_user_id')->nullable();
            $t->string('round_id',64)->unique(); $t->string('game_code',100); $t->string('session_public_id',64);
            $t->string('status',40); $t->string('currency',5)->default('VC'); $t->boolean('is_demo')->default(true);
            $t->decimal('bet_amount',16,6); $t->decimal('win_amount',16,6); $t->decimal('refund_amount',16,6)->default(0);
            $t->string('request_ref',150); $t->uuid('correlation_ref'); $t->json('raw_result'); $t->string('result_summary');
            $t->dateTime('started_at'); $t->dateTime('settled_at'); $t->timestamps();
            $t->unique(['internal_game_session_id','request_ref'],'internal_round_session_request_unique');
        });
        Schema::create('internal_game_round_events',function(Blueprint $t) {
            $t->id(); $t->foreignId('internal_game_round_id')->nullable()->constrained('internal_game_rounds')->restrictOnDelete();
            $t->foreignId('internal_game_session_id')->constrained('internal_game_sessions')->restrictOnDelete();
            $t->string('event_type',100); $t->string('event_status',40); $t->string('request_ref',150)->nullable();
            $t->uuid('correlation_ref')->nullable(); $t->text('message')->nullable(); $t->json('payload'); $t->dateTime('created_at');
        });
        foreach(['internal_game_sessions','internal_game_rounds'] as $table) {
            DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_demo_only CHECK (is_demo = 1 AND currency = 'VC' AND cms_user_id IS NULL AND root_user_id IS NULL)");
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('internal_game_round_events');
        Schema::dropIfExists('internal_game_rounds');
        Schema::dropIfExists('internal_game_sessions');
    }
};
