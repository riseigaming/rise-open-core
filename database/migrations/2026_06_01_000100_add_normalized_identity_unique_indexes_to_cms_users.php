<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'cms_users';
    private const USERNAME_COLUMN = 'username_normalized';
    private const EMAIL_COLUMN = 'email_normalized';
    private const USERNAME_INDEX = 'cms_users_username_normalized_unique';
    private const EMAIL_INDEX = 'cms_users_email_normalized_unique';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->assertNoNormalizedDuplicates('username');
        $this->assertNoNormalizedDuplicates('email');

        if (!Schema::hasColumn(self::TABLE, self::USERNAME_COLUMN)) {
            DB::statement(
                "ALTER TABLE `" . self::TABLE . "` " .
                "ADD COLUMN `" . self::USERNAME_COLUMN . "` VARCHAR(50) " .
                "GENERATED ALWAYS AS (NULLIF(LOWER(TRIM(`username`)), '')) STORED"
            );
        }

        if (!Schema::hasColumn(self::TABLE, self::EMAIL_COLUMN)) {
            DB::statement(
                "ALTER TABLE `" . self::TABLE . "` " .
                "ADD COLUMN `" . self::EMAIL_COLUMN . "` VARCHAR(100) " .
                "GENERATED ALWAYS AS (NULLIF(LOWER(TRIM(`email`)), '')) STORED"
            );
        }

        if (!$this->indexExists(self::USERNAME_INDEX)) {
            DB::statement(
                "ALTER TABLE `" . self::TABLE . "` " .
                "ADD UNIQUE INDEX `" . self::USERNAME_INDEX . "` (`" . self::USERNAME_COLUMN . "`)"
            );
        }

        if (!$this->indexExists(self::EMAIL_INDEX)) {
            DB::statement(
                "ALTER TABLE `" . self::TABLE . "` " .
                "ADD UNIQUE INDEX `" . self::EMAIL_INDEX . "` (`" . self::EMAIL_COLUMN . "`)"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::EMAIL_INDEX)) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` DROP INDEX `" . self::EMAIL_INDEX . "`");
        }

        if ($this->indexExists(self::USERNAME_INDEX)) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` DROP INDEX `" . self::USERNAME_INDEX . "`");
        }

        if (Schema::hasColumn(self::TABLE, self::EMAIL_COLUMN)) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` DROP COLUMN `" . self::EMAIL_COLUMN . "`");
        }

        if (Schema::hasColumn(self::TABLE, self::USERNAME_COLUMN)) {
            DB::statement("ALTER TABLE `" . self::TABLE . "` DROP COLUMN `" . self::USERNAME_COLUMN . "`");
        }
    }

    private function assertNoNormalizedDuplicates(string $column): void
    {
        $duplicates = DB::selectOne(
            "SELECT COUNT(*) AS duplicate_groups FROM (" .
            " SELECT LOWER(TRIM(`" . $column . "`)) AS normalized_identity" .
            " FROM `" . self::TABLE . "`" .
            " WHERE `" . $column . "` IS NOT NULL AND LENGTH(TRIM(`" . $column . "`)) > 0" .
            " GROUP BY LOWER(TRIM(`" . $column . "`))" .
            " HAVING COUNT(*) > 1" .
            ") duplicate_check"
        );

        if ((int) ($duplicates->duplicate_groups ?? 0) > 0) {
            throw new RuntimeException('cms_users_' . $column . '_normalized_duplicates_must_be_cleaned_before_indexing');
        }
    }

    private function indexExists(string $indexName): bool
    {
        $index = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics ' .
            'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [self::TABLE, $indexName]
        );

        return $index !== null;
    }
};
