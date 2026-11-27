<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlacementToCmsSlidersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('cms_sliders', 'placement')) {
            Schema::table('cms_sliders', function (Blueprint $table) {
                $table->string('placement', 20)->default('home')->after('id');
                $table->index(['placement', 'status', 'rank'], 'cms_sliders_placement_status_rank_index');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('cms_sliders', 'placement')) {
            Schema::table('cms_sliders', function (Blueprint $table) {
                $table->dropIndex('cms_sliders_placement_status_rank_index');
                $table->dropColumn('placement');
            });
        }
    }
}
