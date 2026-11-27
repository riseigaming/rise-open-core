<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_settlement_control', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedTinyInteger('id')->primary();
            $table->string('state', 12);
        });
        Schema::create('crypto_settlement_leases', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->string('source', 64);
            $table->char('reference_hash', 64);
            $table->unsignedBigInteger('started_at')->index();
        });
        // Bootstrap preserves existing processing until all old writers have drained.
        // Operators may trust PAUSED only after the gate-aware generation is verified.
        DB::table('crypto_settlement_control')->insert(['id' => 1, 'state' => 'RUNNING']);
    }

    public function down(): void
    {
        if (DB::table('crypto_settlement_leases')->exists()
            || DB::table('crypto_settlement_control')->where('id', 1)->value('state') !== 'PAUSED') {
            throw new RuntimeException('Crypto processing must be paused and drained before rollback.');
        }
        Schema::dropIfExists('crypto_settlement_leases');
        Schema::dropIfExists('crypto_settlement_control');
    }
};
