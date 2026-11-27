<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThemeKeyToCmsSlidersTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_sliders') || Schema::hasColumn('cms_sliders', 'theme_key')) {
            return;
        }

        Schema::table('cms_sliders', function (Blueprint $table): void {
            $table->string('theme_key', 64)->nullable()->after('placement')->index();
            $table->index(['theme_key', 'placement', 'status', 'rank'], 'cms_sliders_theme_placement_status_rank_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_sliders') || !Schema::hasColumn('cms_sliders', 'theme_key')) {
            return;
        }

        Schema::table('cms_sliders', function (Blueprint $table): void {
            $table->dropIndex('cms_sliders_theme_placement_status_rank_idx');
            $table->dropIndex(['theme_key']);
            $table->dropColumn('theme_key');
        });
    }
}
