<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_sessions', function (Blueprint $table) {
            $table->id();
            $table->string("os_name");
            $table->string("browser_name");
            $table->string("user_agent");
            $table->string("ip_address")->nullable();
            $table->string("remember_token")->nullable();

            $table->unsignedBigInteger("cms_user_id")->nullable();
            $table->timestamps();

            $table->foreign("cms_user_id")->references("id")->on("cms_users");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_sessions');
    }
}
