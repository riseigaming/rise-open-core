<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void {
        Schema::table('providers',function(Blueprint $t){$t->json('config')->nullable();$t->unsignedBigInteger('ref_provider_id')->nullable();});
        Schema::table('cms_games',function(Blueprint $t){
            foreach(['detail_image','category_name','type_name']as $c)$t->string($c)->nullable();
            foreach(['images','extra_parameters']as $c)$t->json($c)->nullable();
            $t->boolean('is_featured')->default(false);$t->boolean('show_in_special_games')->default(false);
        });
        Schema::table('external_game_sessions',function(Blueprint $t){
            foreach(['last_callback_command','last_callback_transaction_id','last_callback_status']as $c)$t->string($c)->nullable();
            $t->json('last_callback_payload')->nullable();$t->timestamp('last_callback_at')->nullable();
        });
    }
    public function down():void {
        Schema::table('external_game_sessions',fn(Blueprint $t)=>$t->dropColumn(['last_callback_command','last_callback_transaction_id','last_callback_status','last_callback_payload','last_callback_at']));
        Schema::table('cms_games',fn(Blueprint $t)=>$t->dropColumn(['detail_image','category_name','type_name','images','extra_parameters','is_featured','show_in_special_games']));
        Schema::table('providers',fn(Blueprint $t)=>$t->dropColumn(['config','ref_provider_id']));
    }
};
