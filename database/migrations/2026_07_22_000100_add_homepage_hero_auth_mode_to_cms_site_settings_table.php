<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_site_settings') || Schema::hasColumn('cms_site_settings', 'homepage_hero_auth_mode')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table) {
            $table->string('homepage_hero_auth_mode', 16)->default('login')->after('register_status');
        });

        DB::table('cms_site_settings')
            ->whereNull('homepage_hero_auth_mode')
            ->update(['homepage_hero_auth_mode' => 'login']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_site_settings') || !Schema::hasColumn('cms_site_settings', 'homepage_hero_auth_mode')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table) {
            $table->dropColumn('homepage_hero_auth_mode');
        });
    }
};
