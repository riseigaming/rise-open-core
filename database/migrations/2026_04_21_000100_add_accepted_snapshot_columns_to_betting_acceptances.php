<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAcceptedSnapshotColumnsToBettingAcceptances extends Migration
{
    public function up()
    {
        Schema::table('betting_acceptances', function (Blueprint $table) {
            $table->json('accepted_snapshot_json')->nullable()->after('context');
            $table->string('accepted_snapshot_digest', 64)->nullable()->after('accepted_snapshot_json');
        });
    }

    public function down()
    {
        Schema::table('betting_acceptances', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_snapshot_json',
                'accepted_snapshot_digest',
            ]);
        });
    }
}
