<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBettingIdempotencyAndAliasTables extends Migration
{
    public function up()
    {
        $missingIdempotencyKey = Schema::hasTable('betting_acceptances')
            && !Schema::hasColumn('betting_acceptances', 'idempotency_key');
        $missingRequestFingerprint = Schema::hasTable('betting_acceptances')
            && !Schema::hasColumn('betting_acceptances', 'request_fingerprint');

        if ($missingIdempotencyKey || $missingRequestFingerprint) {
            Schema::table('betting_acceptances', function (Blueprint $table) use ($missingIdempotencyKey, $missingRequestFingerprint) {
                if ($missingIdempotencyKey) {
                    $table->string('idempotency_key', 191)->nullable()->after('acceptance_ref');
                }

                if ($missingRequestFingerprint) {
                    $table->string('request_fingerprint', 64)->nullable()->after($missingIdempotencyKey ? 'idempotency_key' : 'acceptance_ref');
                }

                if ($missingIdempotencyKey) {
                    $table->unique(['idempotency_key'], 'betting_acceptances_idempotency_unique');
                }
            });
        }

        if (Schema::hasTable('betting_source_aliases')) {
            return;
        }

        Schema::create('betting_source_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('adapter_key', 100);
            $table->string('alias_type', 40);
            $table->string('source_context', 191)->nullable();
            $table->string('source_value', 191);
            $table->string('canonical_value', 191);
            $table->string('notes', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['adapter_key', 'alias_type', 'source_context', 'source_value'], 'betting_source_aliases_unique');
            $table->index(['adapter_key', 'alias_type', 'is_active'], 'betting_source_aliases_lookup_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('betting_source_aliases');

        if (!Schema::hasTable('betting_acceptances')) {
            return;
        }

        Schema::table('betting_acceptances', function (Blueprint $table) {
            if (Schema::hasColumn('betting_acceptances', 'idempotency_key')) {
                $table->dropUnique('betting_acceptances_idempotency_unique');
            }
        });

        Schema::table('betting_acceptances', function (Blueprint $table) {
            $dropColumns = [];
            if (Schema::hasColumn('betting_acceptances', 'idempotency_key')) {
                $dropColumns[] = 'idempotency_key';
            }
            if (Schema::hasColumn('betting_acceptances', 'request_fingerprint')) {
                $dropColumns[] = 'request_fingerprint';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
