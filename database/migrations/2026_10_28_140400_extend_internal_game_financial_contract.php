<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  foreach(['internal_game_sessions','internal_game_rounds']as $table){
   DB::statement("ALTER TABLE $table DROP CONSTRAINT {$table}_demo_only");
   DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_mode_owner CHECK ((is_demo = 1 AND currency = 'VC' AND cms_user_id IS NULL AND root_user_id IS NULL) OR (is_demo = 0 AND currency IN ('TRY','USD','EUR') AND cms_user_id IS NOT NULL AND root_user_id IS NOT NULL))");
  }
  Schema::table('internal_game_rounds',function(Blueprint $t){foreach(['debit_transaction_id','win_transaction_id','refund_transaction_id']as $c)$t->unsignedBigInteger($c)->nullable();$t->dateTime('refunded_at')->nullable();$t->text('error_message')->nullable();});
  Schema::create('internal_game_round_bets',function(Blueprint $t){$t->id();$t->foreignId('internal_game_round_id')->constrained('internal_game_rounds')->restrictOnDelete();$t->unsignedInteger('line_no');$t->string('bet_type',40);$t->string('bet_key',120);$t->json('covered_numbers_json');$t->decimal('stake_amount',16,6);$t->decimal('payout_multiplier',16,6);$t->decimal('payout_amount',16,6)->default(0);$t->string('status',40);$t->json('meta_json')->nullable();$t->timestamps();$t->unique(['internal_game_round_id','line_no']);});
 }
 public function down(): void {throw new RuntimeException('Disable financial execution instead; preserve all session, round and ledger history.');}
};
