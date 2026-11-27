<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::create('wallet_user_preferences',function(Blueprint $t){$t->id();$t->unsignedBigInteger('cms_user_id')->unique();$t->unsignedBigInteger('active_wallet_account_id')->nullable();$t->dateTime('changed_at')->nullable();$t->json('meta')->nullable();$t->timestamps();});
  Schema::create('cms_user_transactions',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('cms_user_id');$t->string('transactionId',120)->unique();$t->string('ref_transaction_id',120)->nullable();$t->string('round_id',120)->nullable()->index();
   foreach(['cms_partner_id','promotion_code_id','bonus_id','free_round_id','game_provider_id','game_id','source_wallet_account_id','execution_wallet_account_id','settled_wallet_account_id','created_by_admin']as $c)$t->unsignedBigInteger($c)->nullable();
   $t->smallInteger('action');$t->smallInteger('status')->default(1);$t->decimal('amount',24,8);$t->decimal('before_balance',24,8)->nullable();$t->decimal('after_balance',24,8)->nullable();
   foreach(['exact_amount','exact_before_balance','exact_after_balance','tax_percentage','tax_amount','taxed_amount']as $c)$t->decimal($c,24,8)->nullable();
   foreach(['currency','transaction_asset','source_currency','requested_payout_currency','execution_currency','settled_currency']as $c)$t->string($c,16)->nullable();
   foreach(['event_type','provider_transaction_id','source_wallet_type','payout_route_type','payout_conversion_policy','payout_route_reason','exact_precision_source','acceptor','rejected_reason','payment_api_url']as $c)$t->string($c,255)->nullable();
   foreach(['crypto_deposit_context','payout_context_snapshot','execution_context_snapshot']as $c)$t->json($c)->nullable();
   $t->boolean('payout_requires_conversion')->nullable();$t->unsignedSmallInteger('transaction_precision_scale')->nullable();$t->dateTime('exact_precision_backfilled_at')->nullable();$t->timestamps();
  });
  Schema::create('cms_promotion_codes',function(Blueprint $t){$t->id();$t->string('promo_code',120)->unique();$t->string('currency',16);$t->decimal('promo_amount',24,8);$t->unsignedInteger('promo_count');$t->unsignedInteger('promo_left');$t->dateTime('start_date')->nullable();$t->dateTime('end_date')->nullable();$t->string('status',20)->default('active');$t->timestamps();$t->softDeletes();});
  Schema::create('cms_webhook_logs',function(Blueprint $t){$t->id();$t->longText('request')->nullable();$t->string('message',255);$t->dateTime('reqdate');});
  Schema::create('finance_reset_controls',function(Blueprint $t){$t->unsignedInteger('id')->primary();$t->boolean('writes_closed')->default(false);$t->unsignedInteger('version')->default(0);foreach(['declaration_reference','applied_plan_sha256','isolation_proof_sha256']as $c)$t->string($c)->nullable();$t->dateTime('closed_at')->nullable();$t->dateTime('opened_at')->nullable();$t->timestamps();});
  DB::table('finance_reset_controls')->insert(['id'=>1,'writes_closed'=>false,'version'=>0,'created_at'=>now(),'updated_at'=>now()]);
  Schema::table('cms_users',function(Blueprint $t){$t->decimal('balance',24,8)->default(0)->change();});
 }
 public function down(): void { throw new RuntimeException('Financial history is retained; no automatic destructive rollback.'); }
};
