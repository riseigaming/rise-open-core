<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSiteSettingTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_site_setting_translations', function (Blueprint $table) {
            $table->id();

            $table->string("site_title")->nullable();
            $table->string("site_description")->nullable();
            $table->string("first_modal_image_lg")->nullable();
            $table->string("first_modal_image_sm")->nullable();
            $table->string("first_modal_text")->nullable();
            $table->unsignedBigInteger("cms_language_id")->nullable();
            $table->unsignedBigInteger("cms_setting_id")->nullable();

            $table->timestamps();

            $table->foreign("cms_language_id")->references("id")->on("cms_languages");
            $table->foreign("cms_setting_id")->references("id")->on("cms_site_settings");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_site_setting_translations');
    }
}
