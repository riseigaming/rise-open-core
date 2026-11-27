<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('internal_game_rounds',function(Blueprint $t){$t->dateTime('settled_at')->nullable()->change();});}
 public function down():void{throw new RuntimeException('Preserve open and settled financial rounds; disable gameplay instead of destructive rollback.');}
};
