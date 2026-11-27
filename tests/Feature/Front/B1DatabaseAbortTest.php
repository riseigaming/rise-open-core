<?php
namespace Tests\Feature\Front;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class B1DatabaseAbortTest extends TestCase
{
    public function test_transaction_conflict_cannot_escape_to_later_sql_or_commit(): void
    {
        $connection = DB::connection(); $connection->beginTransaction();
        try {
            try { $connection->statement("SIGNAL SQLSTATE '40001' SET MYSQL_ERRNO=1213, MESSAGE_TEXT='B1 local conflict fixture'"); $this->fail('Conflict fixture accepted'); }
            catch (\PDOException $error) { $this->assertTrue(\App\Database\MySqlConnection::isTransactionConflict($error)); }
            try { $connection->select('SELECT 1'); $this->fail('SQL escaped an aborted transaction'); }
            catch (\PDOException $error) { $this->assertTrue(\App\Database\MySqlConnection::isTransactionConflict($error)); }
            try { $connection->commit(); $this->fail('Aborted transaction committed'); }
            catch (\PDOException $error) { $this->assertTrue(\App\Database\MySqlConnection::isTransactionConflict($error)); }
        } finally { $connection->rollBack(0); }
        $this->assertSame(0, $connection->transactionLevel());
        $this->assertSame(1, (int) $connection->selectOne('SELECT 1 AS value')->value);
    }
}
