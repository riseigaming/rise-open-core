<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsSlidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_sliders', function (Blueprint $table) {
            $table->id();
            $table->integer("rank")->default(1);
            $table->tinyInteger("status")->default(1);
            $table->string("image_path")->nullable();
            $table->string("image_name")->nullable();
            $table->tinyInteger("use_same_image")->default(0)->comment("1: Tüm dil içeriklerinde aynı resmi kullanır");
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
        Schema::dropIfExists('cms_sliders');
    }
}
