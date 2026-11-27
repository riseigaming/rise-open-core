<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsPromotionTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_promotion_translations', function (Blueprint $table) {
            $table->id();
            $table->string("listing_title");
            $table->string("detail_title");
            $table->text("short_description");
            $table->text("description");
            $table->string("redirect_button_text")->nullable();
            $table->unsignedBigInteger("cms_promotion_id")->nullable();
            $table->unsignedBigInteger("cms_language_id")->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign("cms_language_id")->references("id")->on("cms_languages");
            $table->foreign("cms_promotion_id")->references("id")->on("cms_promotions");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_promotion_translations');
    }
}
