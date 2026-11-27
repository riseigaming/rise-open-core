<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_site_settings')) {
            return;
        }

        if (!Schema::hasColumn('cms_site_settings', 'menu_version')) {
            Schema::table('cms_site_settings', function (Blueprint $table) {
                $table->string('menu_version', 10)->default('1')->after('active_theme');
            });
        }

        DB::table('cms_site_settings')
            ->whereNull('menu_version')
            ->update(['menu_version' => '1']);
    }

    public function down(): void
    {
        // Intentionally left non-destructive for recovered local state.
    }
};
