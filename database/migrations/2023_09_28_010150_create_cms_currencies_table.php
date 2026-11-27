<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsCurrenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return voidæ
     */
    public function up()
    {
        Schema::create('cms_currencies', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("code");
            $table->string("symbol");
            $table->float("rate", 16,8);
            $table->tinyInteger("is_default")->default(0);
            $table->enum("status",  ["active", "passive"])->default("active");
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_currencies');
    }
}
