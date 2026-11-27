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
            if (! Schema::hasColumn('cms_site_settings', 'account_mail_status')) {
                $table->string('account_mail_status', 10)
                    ->default('active');
            }
        });

        DB::table('cms_site_settings')
            ->whereNull('account_mail_status')
            ->update(['account_mail_status' => 'active']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_site_settings')) {
            return;
        }

        Schema::table('cms_site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_site_settings', 'account_mail_status')) {
                $table->dropColumn('account_mail_status');
            }
        });
    }
};
