<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChessMarketExposureScopesTable extends Migration
{
    public function up()
    {
        Schema::create('chess_market_exposure_scopes', function (Blueprint $table) {
            $table->id();
            $table->char('scope_key', 64)->unique();
            $table->string('market_instance_id', 191);
            $table->string('currency', 10);
            $table->timestamps();
        });

        Schema::table('chess_wallet_reservations', function (Blueprint $table) {
            $table->index(
                ['market_instance_id', 'currency', 'status'],
                'chess_wallet_exposure_lookup'
            );
        });
    }

    public function down()
    {
        Schema::table('chess_wallet_reservations', function (Blueprint $table) {
            $table->dropIndex('chess_wallet_exposure_lookup');
        });

        Schema::dropIfExists('chess_market_exposure_scopes');
    }
}
