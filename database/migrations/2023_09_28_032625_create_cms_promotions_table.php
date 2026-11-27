<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsPromotionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_promotions', function (Blueprint $table) {
            $table->id();
            $table->string("thumbnail");
            $table->string("detail_image");
            $table->string("redirect_url")->nullable();
            $table->enum("status", ["active", "passive"])->default("active");
            $table->tinyInteger("is_permanent")->default(0);
            $table->dateTime("end_date")->nullable();
            $table->integer("rank")->default(0);
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
        Schema::dropIfExists('cms_promotions');
    }
}
