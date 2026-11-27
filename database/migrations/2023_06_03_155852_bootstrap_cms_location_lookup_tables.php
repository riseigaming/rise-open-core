<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BootstrapCmsLocationLookupTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->createLookupTableIfMissing('cms_countries');
        $this->createLookupTableIfMissing('cms_cities');
        $this->createLookupTableIfMissing('cms_states');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // These lookup tables are shared and may contain real data on long-lived
        // environments, so this bootstrap migration deliberately leaves them in
        // place on rollback.
    }

    private function createLookupTableIfMissing(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
}
