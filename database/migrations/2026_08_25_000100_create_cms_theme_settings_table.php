<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsThemeSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('cms_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('theme_key', 64)->unique();
            $table->string('logo')->nullable();
            $table->json('translations')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cms_theme_settings');
    }
}
