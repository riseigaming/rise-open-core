<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'site_settings_key_id_index';

    public function up(): void
    {
        if (!Schema::hasTable('site_settings') || DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Rollout index requires an existing MySQL/MariaDB site_settings table.');
        }

        $table = DB::selectOne(
            "SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings'"
        );
        $key = DB::selectOne(
            "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings' AND COLUMN_NAME = 'key'"
        );
        $id = DB::selectOne(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings' AND COLUMN_NAME = 'id'"
        );

        if (($table->ENGINE ?? '') !== 'InnoDB'
            || strtolower((string) ($key->DATA_TYPE ?? '')) !== 'varchar'
            || (int) ($key->CHARACTER_MAXIMUM_LENGTH ?? 0) !== 255
            || (string) ($key->CHARACTER_SET_NAME ?? '') !== 'utf8mb4'
            || !preg_match('/\A[a-zA-Z0-9_]+\z/D', (string) ($key->COLLATION_NAME ?? ''))
            || $id === null) {
            throw new RuntimeException('Unexpected site_settings schema; refusing rollout index change.');
        }

        $indexes = DB::select(
            "SELECT COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings' AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX",
            [self::INDEX]
        );
        if ($indexes !== []) {
            if (count($indexes) === 2
                && (string) $indexes[0]->COLUMN_NAME === 'key'
                && (string) $indexes[1]->COLUMN_NAME === 'id'
                && (int) $indexes[0]->SEQ_IN_INDEX === 1
                && (int) $indexes[1]->SEQ_IN_INDEX === 2
                && (int) $indexes[0]->NON_UNIQUE === 1) {
                return;
            }

            throw new RuntimeException('Existing rollout index name has incompatible definition.');
        }

        DB::statement('CREATE INDEX '.self::INDEX.' ON site_settings ('.chr(96).'key'.chr(96).', id)');
    }

    public function down(): void
    {
        if (Schema::hasTable('site_settings') && DB::getDriverName() === 'mysql') {
            $indexes = DB::select(
                "SELECT COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings' AND INDEX_NAME = ?
                 ORDER BY SEQ_IN_INDEX",
                [self::INDEX]
            );
            if ($indexes !== []) {
                if (count($indexes) !== 2
                    || (string) $indexes[0]->COLUMN_NAME !== 'key'
                    || (string) $indexes[1]->COLUMN_NAME !== 'id'
                    || (int) $indexes[0]->SEQ_IN_INDEX !== 1
                    || (int) $indexes[1]->SEQ_IN_INDEX !== 2
                    || (int) $indexes[0]->NON_UNIQUE !== 1) {
                    throw new RuntimeException('Refusing to drop a different site_settings index.');
                }
                DB::statement('DROP INDEX '.self::INDEX.' ON site_settings');
            }
        }
    }
};
