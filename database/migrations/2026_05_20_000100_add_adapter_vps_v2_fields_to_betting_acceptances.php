<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAdapterVpsV2FieldsToBettingAcceptances extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('betting_acceptances')) {
            return;
        }

        $this->makeLegacyIdentityColumnsNullable();

        Schema::table('betting_acceptances', function (Blueprint $table) {
            if (!Schema::hasColumn('betting_acceptances', 'acceptance_contract_version')) {
                $table->string('acceptance_contract_version', 30)->default('legacy_v1')->after('acceptance_ref');
            }

            if (!Schema::hasColumn('betting_acceptances', 'acceptance_source')) {
                $table->string('acceptance_source', 40)->default('cms_legacy')->after('acceptance_contract_version');
            }

            if (!Schema::hasColumn('betting_acceptances', 'quote_ref')) {
                $table->string('quote_ref', 191)->nullable()->after('request_fingerprint');
                $table->index(['quote_ref'], 'betting_acceptances_quote_ref_idx');
            }

            if (!Schema::hasColumn('betting_acceptances', 'quote_version_ref')) {
                $table->string('quote_version_ref', 191)->nullable()->after('quote_ref');
                $table->index(['quote_version_ref'], 'betting_acceptances_quote_version_ref_idx');
            }

            if (!Schema::hasColumn('betting_acceptances', 'adapter_verification_id')) {
                $table->string('adapter_verification_id', 191)->nullable()->after('quote_version_ref');
                $table->index(['adapter_verification_id'], 'betting_acceptances_adapter_verification_id_idx');
            }

            if (!Schema::hasColumn('betting_acceptances', 'adapter_verification_digest')) {
                $table->string('adapter_verification_digest', 64)->nullable()->after('adapter_verification_id');
                $table->index(['adapter_verification_digest'], 'betting_acceptances_adapter_verification_digest_idx');
            }

            if (!Schema::hasColumn('betting_acceptances', 'adapter_payload_digest')) {
                $table->string('adapter_payload_digest', 64)->nullable()->after('adapter_verification_digest');
            }

            if (!Schema::hasColumn('betting_acceptances', 'source_provider_key')) {
                $table->string('source_provider_key', 100)->nullable()->after('adapter_payload_digest');
                $table->index(['source_provider_key'], 'betting_acceptances_source_provider_key_idx');
            }

            if (!Schema::hasColumn('betting_acceptances', 'connector_key')) {
                $table->string('connector_key', 120)->nullable()->after('source_provider_key');
                $table->index(['connector_key'], 'betting_acceptances_connector_key_idx');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('betting_acceptances')) {
            return;
        }

        Schema::table('betting_acceptances', function (Blueprint $table) {
            foreach ([
                'betting_acceptances_quote_ref_idx',
                'betting_acceptances_quote_version_ref_idx',
                'betting_acceptances_adapter_verification_id_idx',
                'betting_acceptances_adapter_verification_digest_idx',
                'betting_acceptances_source_provider_key_idx',
                'betting_acceptances_connector_key_idx',
            ] as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Throwable $exception) {
                    // Index names vary across local engines; keep rollback best-effort.
                }
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('betting_acceptances', 'acceptance_contract_version') ? 'acceptance_contract_version' : null,
                Schema::hasColumn('betting_acceptances', 'acceptance_source') ? 'acceptance_source' : null,
                Schema::hasColumn('betting_acceptances', 'quote_ref') ? 'quote_ref' : null,
                Schema::hasColumn('betting_acceptances', 'quote_version_ref') ? 'quote_version_ref' : null,
                Schema::hasColumn('betting_acceptances', 'adapter_verification_id') ? 'adapter_verification_id' : null,
                Schema::hasColumn('betting_acceptances', 'adapter_verification_digest') ? 'adapter_verification_digest' : null,
                Schema::hasColumn('betting_acceptances', 'adapter_payload_digest') ? 'adapter_payload_digest' : null,
                Schema::hasColumn('betting_acceptances', 'source_provider_key') ? 'source_provider_key' : null,
                Schema::hasColumn('betting_acceptances', 'connector_key') ? 'connector_key' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function makeLegacyIdentityColumnsNullable(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ([
            'betting_quote_version_id',
            'betting_quote_selection_id',
            'betting_event_id',
            'betting_market_id',
            'betting_outcome_id',
        ] as $column) {
            DB::statement(sprintf(
                'ALTER TABLE betting_acceptances MODIFY %s BIGINT UNSIGNED NULL',
                $column
            ));
        }
    }
}
