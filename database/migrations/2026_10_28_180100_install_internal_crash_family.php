<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('internal_game_crash_rounds',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('provider_id')->nullable();$t->unsignedBigInteger('cms_game_id');
   $t->string('round_public_id',64)->unique();$t->string('game_code',100);$t->string('status',40)->default('betting');$t->string('currency',5)->default('TRY');$t->boolean('is_demo')->default(false);
   $t->decimal('crash_multiplier',10,2)->default(1);foreach(['server_seed','server_seed_hash','fair_hash']as $f)$t->string($f,128)->nullable();
   foreach(['betting_opens_at','betting_closes_at','starts_at','crashed_at','settled_at']as $f)$t->dateTime($f,3)->nullable();
   $t->json('raw_result')->nullable();$t->timestamps();$t->index(['cms_game_id','game_code','currency','is_demo','status'],'internal_crash_round_scope_idx');$t->index('crashed_at');
  });
  Schema::table('internal_game_rounds',function(Blueprint $t){
   $t->unsignedBigInteger('internal_game_crash_round_id')->nullable()->index('internal_round_crash_round_idx');$t->unsignedTinyInteger('bet_lane')->default(1);
   $t->string('cashout_request_ref',150)->nullable()->index('internal_round_cashout_request_idx');$t->decimal('cashout_multiplier',10,2)->default(0);$t->decimal('cashout_amount',16,6)->default(0);$t->dateTime('cashed_out_at',3)->nullable();
   $t->unique(['internal_game_session_id','internal_game_crash_round_id','bet_lane'],'internal_round_session_crash_round_lane_unique');
  });
 }
 public function down():void {Schema::table('internal_game_rounds',function(Blueprint $t){$t->dropUnique('internal_round_session_crash_round_lane_unique');$t->dropIndex('internal_round_crash_round_idx');$t->dropIndex('internal_round_cashout_request_idx');$t->dropColumn(['internal_game_crash_round_id','bet_lane','cashout_request_ref','cashout_multiplier','cashout_amount','cashed_out_at']);});Schema::dropIfExists('internal_game_crash_rounds');}
};
