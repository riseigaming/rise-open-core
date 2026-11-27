<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSiteSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_site_settings', function (Blueprint $table) {
            $table->id();
            $table->enum("chat_status", ["active", "passive"])->default("passive");
            $table->enum("splash_status", ["active", "passive"])->default("passive");
            $table->enum("balance_status", ["active", "passive"])->default("passive");
            $table->string("socket_url")->nullable();
            $table->string("logo")->nullable();
            $table->string("socket_port", 10)->nullable();
            $table->string("active_theme")->nullable()->default("global");
            $table->string("default_lang")->default("en");
            $table->tinyInteger("register_status")->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_site_settings');
    }
}
