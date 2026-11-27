<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('cms_promotions', 'show_on_homepage_top')) {
                $table->boolean('show_on_homepage_top')->default(false)->after('show_in_top_line');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms_promotions', function (Blueprint $table) {
            if (Schema::hasColumn('cms_promotions', 'show_on_homepage_top')) {
                $table->dropColumn('show_on_homepage_top');
            }
        });
    }
};
