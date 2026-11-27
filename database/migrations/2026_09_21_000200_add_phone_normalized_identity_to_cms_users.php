<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_users')) return;
        if (!Schema::hasColumn('cms_users', 'phone_normalized')) {
            Schema::table('cms_users', function (Blueprint $table): void {
                $table->string('phone_normalized', 32)->nullable()->after('phone');
            });
        }
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->unique('phone_normalized', 'cms_users_phone_normalized_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_users') && Schema::hasColumn('cms_users', 'phone_normalized')) {
            Schema::table('cms_users', function (Blueprint $table): void {
                $table->dropUnique('cms_users_phone_normalized_unique');
                $table->dropColumn('phone_normalized');
            });
        }
    }
};
