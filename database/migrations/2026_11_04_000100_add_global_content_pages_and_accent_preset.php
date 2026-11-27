<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->boolean('published')->default(false);
            $table->json('translations');
            $table->timestamps();
        });

        Schema::table('cms_theme_settings', function (Blueprint $table): void {
            $table->string('accent_preset', 16)->default('default');
        });
    }

    public function down(): void
    {
        Schema::table('cms_theme_settings', function (Blueprint $table): void {
            $table->dropColumn('accent_preset');
        });
        Schema::dropIfExists('cms_content_pages');
    }
};
