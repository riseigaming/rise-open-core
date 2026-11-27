<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_settlement_claims', function (Blueprint $table) {
            $table->id();
            $table->char('output_fingerprint', 64)->unique();
            $table->unsignedBigInteger('cms_transaction_id')->unique();
            $table->string('provider', 32);
            $table->string('provider_reference', 120);
            $table->string('session_id', 120);
            $table->string('rail_key', 32);
            $table->string('asset', 16);
            $table->string('network', 16);
            $table->string('network_mode', 16);
            $table->char('tx_hash', 64);
            $table->string('output_kind', 40);
            $table->string('address', 128);
            $table->decimal('exact_amount', 24, 8);
            $table->timestamps();
            $table->index(['provider', 'provider_reference'], 'crypto_claim_provider_reference_idx');
            $table->index(['tx_hash', 'network_mode'], 'crypto_claim_hash_network_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('crypto_settlement_claims')->exists()) {
            throw new RuntimeException('Settlement ownership claims must be retained; rollback code without dropping populated claims.');
        }
        Schema::dropIfExists('crypto_settlement_claims');
    }
};
