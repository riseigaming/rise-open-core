<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlignCmsLocationsFreshInstallColumns extends Migration
{
    public function up()
    {
        foreach (['cms_countries', 'cms_states', 'cms_cities'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'name')) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table): void {
                $table->string('name')->nullable();
            });
        }

        if (Schema::hasTable('cms_countries') && ! Schema::hasColumn('cms_countries', 'iso2')) {
            Schema::table('cms_countries', static function (Blueprint $table): void {
                $table->string('iso2', 2)->nullable()->unique();
            });
        }

        if (Schema::hasTable('cms_states') && ! Schema::hasColumn('cms_states', 'country_id')) {
            Schema::table('cms_states', static function (Blueprint $table): void {
                $table->unsignedBigInteger('country_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('cms_cities')) {
            $needsCountry = ! Schema::hasColumn('cms_cities', 'country_id');
            $needsState = ! Schema::hasColumn('cms_cities', 'state_id');
            if ($needsCountry || $needsState) {
                Schema::table('cms_cities', static function (Blueprint $table) use ($needsCountry, $needsState): void {
                    if ($needsCountry) {
                        $table->unsignedBigInteger('country_id')->nullable()->index();
                    }
                    if ($needsState) {
                        $table->unsignedBigInteger('state_id')->nullable()->index();
                    }
                });
            }
        }
    }

    public function down()
    {
        // Existing installations may own these lookup columns; never remove
        // them as a side effect of rolling back a fresh-install alignment.
    }
}
