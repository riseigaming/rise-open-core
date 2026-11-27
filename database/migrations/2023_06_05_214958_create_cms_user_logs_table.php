<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsUserLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_user_logs', function (Blueprint $table) {
            $table->id();
            $table->string("log_type");

            $table->string("title")->nullable();
            $table->text("description")->nullable();
            $table->unsignedBigInteger("cms_user_id")->nullable()->comment("Mevcut işlemin ilgilendirdiği kullanıcı");
            $table->unsignedBigInteger("cms_operator_id")->nullable()->comment("İşlemi bir başkası gerçekleştirdiyse");

            $table->timestamps();

            $table->foreign("cms_user_id")->references("id")->on("cms_users")->onDelete("cascade");
            $table->foreign("cms_operator_id")->references("id")->on("cms_users")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_user_logs');
    }
}
