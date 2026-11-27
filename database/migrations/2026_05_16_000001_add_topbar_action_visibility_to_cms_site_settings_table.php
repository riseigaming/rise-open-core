<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_site_settings')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_site_settings', 'topbar_fullscreen_status')) {
                $table->string('topbar_fullscreen_status', 10)->default('active')->after('balance_status');
            }

            if (! Schema::hasColumn('cms_site_settings', 'topbar_logout_status')) {
                $table->string('topbar_logout_status', 10)->default('active')->after('topbar_fullscreen_status');
            }
        });

        DB::table('cms_site_settings')
            ->whereNull('topbar_fullscreen_status')
            ->update(['topbar_fullscreen_status' => 'active']);

        DB::table('cms_site_settings')
            ->whereNull('topbar_logout_status')
            ->update(['topbar_logout_status' => 'active']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_site_settings')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_site_settings', 'topbar_logout_status')) {
                $table->dropColumn('topbar_logout_status');
            }

            if (Schema::hasColumn('cms_site_settings', 'topbar_fullscreen_status')) {
                $table->dropColumn('topbar_fullscreen_status');
            }
        });
    }
};
