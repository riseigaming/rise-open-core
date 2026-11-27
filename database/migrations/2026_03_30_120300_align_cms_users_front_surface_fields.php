<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_users')) {
            return;
        }

        if (!Schema::hasColumn('cms_users', 'created_by_admin')) {
            Schema::table('cms_users', function (Blueprint $table) {
                $table->tinyInteger('created_by_admin')->default(0)->after('parent_id');
            });
        }

        $emailVerifyHashColumn = DB::selectOne("SHOW COLUMNS FROM cms_users LIKE 'email_verify_hash'");
        if ($emailVerifyHashColumn && stripos((string) ($emailVerifyHashColumn->Type ?? ''), 'varchar') === false) {
            DB::statement('ALTER TABLE cms_users MODIFY email_verify_hash VARCHAR(150) NULL');
        }
    }

    public function down(): void
    {
        // Intentionally left non-destructive for recovered local state.
    }
};
