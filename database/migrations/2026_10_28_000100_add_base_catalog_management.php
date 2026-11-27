<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $t) {
            $t->id(); $t->string('name', 120); $t->string('slug', 120)->unique();
            $t->string('kind', 20)->default('external'); $t->text('description')->nullable();
            $t->string('status', 20)->default('passive')->index();
            $t->boolean('show_left_panel')->default(true); $t->boolean('is_maintenance')->default(false);
            $t->unsignedInteger('rank')->default(0); $t->timestamps();
        });
        Schema::create('cms_games', function (Blueprint $t) {
            $t->id(); $t->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $t->string('name', 160); $t->string('slug', 160)->unique();
            $t->string('game_type', 20)->default('slot')->index(); $t->string('reference_id', 160)->nullable();
            $t->text('description')->nullable(); $t->string('thumbnail_image')->nullable();
            $t->string('status', 20)->default('passive')->index(); $t->boolean('is_restricted')->default(false);
            $t->boolean('is_maintenance')->default(false); $t->unsignedInteger('rank')->default(0)->index();
            $t->timestamps();
        });
        Schema::create('game_curation_surfaces', function (Blueprint $t) {
            $t->id(); $t->string('surface_key', 80)->unique(); $t->string('title', 160);
            $t->string('status', 20)->default('active'); $t->boolean('auto_fill_enabled')->default(false); $t->timestamps();
        });
        Schema::create('game_curation_items', function (Blueprint $t) {
            $t->id(); $t->string('surface_key', 80); $t->foreign('surface_key')->references('surface_key')->on('game_curation_surfaces')->cascadeOnDelete();
            $t->foreignId('game_id')->constrained('cms_games')->cascadeOnDelete(); $t->unsignedInteger('rank');
            $t->timestamps(); $t->unique(['surface_key', 'game_id']);
        });
        Schema::create('cms_user_product_access_overrides', function (Blueprint $t) {
            $t->id(); $t->foreignId('cms_user_id')->constrained('cms_users')->cascadeOnDelete();
            $t->string('product_key', 40); $t->string('provider_alias', 120)->default('');
            $t->string('access_mode', 10); $t->string('reason', 255);
            $t->foreignId('updated_by')->nullable()->constrained('cms_users')->nullOnDelete(); $t->timestamps();
            $t->unique(['cms_user_id', 'product_key', 'provider_alias'], 'catalog_user_product_provider_unique');
        });
        DB::table('game_curation_surfaces')->insert([
            ['surface_key'=>'casino_all_games','title'=>'Oyun kataloğu','status'=>'active','auto_fill_enabled'=>true,'created_at'=>now(),'updated_at'=>now()],
            ['surface_key'=>'home_special_games','title'=>'Editörün seçimleri','status'=>'active','auto_fill_enabled'=>false,'created_at'=>now(),'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        foreach (['cms_user_product_access_overrides','game_curation_items','game_curation_surfaces','cms_games','providers'] as $table) Schema::dropIfExists($table);
    }
};
