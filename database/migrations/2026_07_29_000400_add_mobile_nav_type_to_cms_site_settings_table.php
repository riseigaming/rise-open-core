<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_site_settings') || Schema::hasColumn('cms_site_settings', 'mobile_nav_type')) {
            return;
        }

        $afterColumn = Schema::hasColumn('cms_site_settings', 'menu_version')
            ? 'menu_version'
            : 'active_theme';

        Schema::table('cms_site_settings', function (Blueprint $table) use ($afterColumn): void {
            $column = $table->string('mobile_nav_type', 20)->default('type_1');

            if (Schema::hasColumn('cms_site_settings', $afterColumn)) {
                $column->after($afterColumn);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_site_settings') || ! Schema::hasColumn('cms_site_settings', 'mobile_nav_type')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table): void {
            $table->dropColumn('mobile_nav_type');
        });
    }
};
