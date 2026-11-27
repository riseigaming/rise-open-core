<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_promotions', function (Blueprint $table) {
            $table->boolean('show_in_top_line')->default(false)->after('is_permanent');
        });

        DB::table('cms_promotions')
            ->where('status', 'active')
            ->where('is_permanent', 1)
            ->update(['show_in_top_line' => 1]);
    }

    public function down(): void
    {
        Schema::table('cms_promotions', function (Blueprint $table) {
            $table->dropColumn('show_in_top_line');
        });
    }
};
