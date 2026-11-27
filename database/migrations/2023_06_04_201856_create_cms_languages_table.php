<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCmsLanguagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_languages', function (Blueprint $table) {
            $table->id();
            $table->string("name", 30);
            $table->string("code", 5);
            $table->tinyInteger("status")->default(1);
            $table->tinyInteger("is_default")->default(1);
            $table->timestamps();
        });

        // A new CMS needs one active default locale for login and admin UI.
        // This is generic reference data; additional locales are optional.
        DB::table('cms_languages')->insert([
            'id' => 1,
            'name' => 'Türkçe',
            'code' => 'tr',
            'status' => 1,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_languages');
    }
}
