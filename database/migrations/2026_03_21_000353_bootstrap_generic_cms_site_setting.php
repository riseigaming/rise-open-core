<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapGenericCmsSiteSetting extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cms_site_settings') || DB::table('cms_site_settings')->exists()) {
            return;
        }

        // Do not turn an existing, content-bearing installation public just
        // because its site-setting row is temporarily absent.
        foreach (['cms_users', 'cms_promotions'] as $tableName) {
            if (Schema::hasTable($tableName) && DB::table($tableName)->exists()) {
                return;
            }
        }

        DB::transaction(static function (): void {
            $settingId = DB::table('cms_site_settings')->insertGetId([
                'default_lang' => 'tr',
                'active_theme' => 'global',
                'register_status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! Schema::hasTable('cms_site_setting_translations') || ! Schema::hasTable('cms_languages')) {
                return;
            }

            $languageId = DB::table('cms_languages')->where('code', 'tr')->value('id');
            if ($languageId) {
                DB::table('cms_site_setting_translations')->insert([
                    'cms_setting_id' => $settingId,
                    'cms_language_id' => $languageId,
                    'site_title' => 'New CMS',
                    'site_description' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down()
    {
        // Settings may be customized after installation. Never delete them
        // automatically during a rollback.
    }
}
