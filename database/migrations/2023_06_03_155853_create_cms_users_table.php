<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cms_users', function (Blueprint $table) {
            $table->id();
            $table->string("first_name", 50)->nullable();
            $table->string("last_name", 50)->nullable();
            $table->string("username", 50)->nullable();
            $table->string("email", 100)->nullable();
            $table->string("phone", 100)->nullable();
            $table->string("email_verify_hash", 150)->nullable();
            $table->dateTime("email_verified_at")->nullable();
            $table->date("birth_date")->nullable();
            $table->string("birth_city", 30)->nullable();
            $table->unsignedBigInteger("country_id")->nullable();
            $table->unsignedBigInteger("city_id")->nullable();
            $table->unsignedBigInteger("state_id")->nullable();
            $table->string("password", 150)->nullable();
            $table->string("password_raw", 150)->nullable();
            $table->string("language", 15)->nullable();
            $table->string("currency", 15)->nullable()->default("USD");
            $table->string("registration_source", 300)->nullable();
            $table->unsignedBigInteger("personal_id")->nullable();
            $table->unsignedBigInteger("player_id")->nullable();
            $table->unsignedBigInteger("external_id")->nullable();
            $table->unsignedBigInteger("partner_id")->nullable();
            $table->unsignedBigInteger("promotion_code_id")->nullable();
            $table->unsignedBigInteger("promotion_link_id")->nullable();
            $table->unsignedBigInteger("promotion_source_id")->nullable();
            $table->unsignedBigInteger("user_type_id")->nullable();
            $table->decimal("balance", 10, 6)->default(0);
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->enum("gender", ["male", "female"])->nullable();
            $table->enum("status", ["accepted", "normal", "fully_accepted", "rejected"])->default("normal");
            $table->enum("role", ["admin", "customer"])->default("customer");
            $table->timestamp("last_seen")->nullable();
            $table->integer("activity_point")->default(0);
            $table->integer("is_chat_blocked")->default(0);
            $table->tinyInteger("is_private")->default(0);
            $table->string("user_color")->default("#a2252d");
            $table->string("remember_token")->nullable();
            $table->decimal("slot_bet_limit", 16,8)->default(0);
            $table->decimal("live_bet_limit", 16,8)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign("user_type_id")->references("id")->on("cms_user_types");
            $table->foreign("country_id")->references("id")->on("cms_countries");
            $table->foreign("city_id")->references("id")->on("cms_cities");
            $table->foreign("state_id")->references("id")->on("cms_states");
            $table->foreign("parent_id")->references("id")->on("cms_users");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cms_users');
    }
}
