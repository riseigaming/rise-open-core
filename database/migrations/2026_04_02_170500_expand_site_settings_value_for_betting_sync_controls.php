<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        DB::statement('ALTER TABLE `site_settings` MODIFY `value` TEXT NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('site_settings')) {
            return;
        }

        if (DB::table('site_settings')->whereRaw('CHAR_LENGTH(`value`) > 255')->exists()) {
            throw new RuntimeException('Cannot narrow site_settings.value: values longer than 255 characters exist.');
        }

        DB::statement('ALTER TABLE `site_settings` MODIFY `value` VARCHAR(255) NOT NULL');
    }
};
