<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_users') || Schema::hasColumn('cms_users', 'is_smoke_account')) return;
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->boolean('is_smoke_account')->default(false);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_users') || !Schema::hasColumn('cms_users', 'is_smoke_account')) return;
        Schema::table('cms_users', function (Blueprint $table): void {
            $table->dropColumn('is_smoke_account');
        });
    }
};
