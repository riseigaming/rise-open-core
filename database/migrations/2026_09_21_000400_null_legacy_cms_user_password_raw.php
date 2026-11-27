<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_users') || !Schema::hasColumn('cms_users', 'password_raw')) {
            return;
        }

        // Values are neither read nor logged. The password hash is deliberately
        // untouched; reset remains the supported recovery mechanism.
        DB::table('cms_users')->whereNotNull('password_raw')->update(['password_raw' => null]);
    }

    public function down(): void
    {
        // Plaintext credentials are irrecoverably removed by design.
    }
};
