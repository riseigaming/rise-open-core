<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSliderTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_slider_translations', function (Blueprint $table) {
            $table->id();
            $table->string("title")->nullable();
            $table->text("description")->nullable();
            $table->string("image_path")->nullable();
            $table->string("image_name")->nullable();
            $table->string("url")->nullable();
            $table->unsignedBigInteger("cms_language_id")->nullable();
            $table->unsignedBigInteger("cms_slider_id")->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign("cms_language_id")->references("id")->on("cms_languages")->onDelete("cascade");
            $table->foreign("cms_slider_id")->references("id")->on("cms_sliders")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_slider_translations');
    }
}
