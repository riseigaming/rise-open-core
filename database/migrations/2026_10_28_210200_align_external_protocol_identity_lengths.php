<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void {
        // Source external IDs are up to 191 bytes; operation-prefixed identities need 255.
        Schema::table('cms_user_transactions',function(Blueprint $t){
            $t->string('transactionId',255)->change();
            $t->string('ref_transaction_id',255)->nullable()->change();
            $t->string('round_id',255)->nullable()->change();
        });
        Schema::table('wallet_ledger_entries',function(Blueprint $t){
            $t->string('source_reference',255)->nullable()->change();
            $t->string('idempotency_key',255)->change();
        });
        Schema::table('cms_games',function(Blueprint $t){
            $t->string('reference_id',255)->nullable()->change();
            $t->string('name',255)->change();$t->string('slug',255)->change();
        });
    }
    public function down():void {
        // Restoring narrower identities could truncate committed provider references.
        throw new RuntimeException('Restore the isolated pre-migration database snapshot instead of truncating financial identities.');
    }
};
