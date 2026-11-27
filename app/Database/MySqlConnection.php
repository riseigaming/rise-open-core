<?php

namespace App\Database;

use PDOException;
use Throwable;
use Closure;

/** Compatibility with server-side transaction aborts on the installed Laravel version. */
class MySqlConnection extends \Illuminate\Database\MySqlConnection
{
    private ?Throwable $abortedTransaction = null;

    protected function run($query, $bindings, Closure $callback)
    {
        $this->assertTransactionUsable();
        try {
            return parent::run($query, $bindings, $callback);
        } catch (Throwable $e) {
            if ($this->transactionLevel() > 0 && self::isTransactionConflict($e)) {
                // Best-effort readers/telemetry may catch SQL errors. Once the server
                // has aborted, never let their later writes escape into autocommit.
                // PDO can still report inTransaction=true immediately after 1020.
                $this->abortedTransaction = $e;
            }
            throw $e;
        }
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        return parent::transaction(function ($connection) use ($callback) {
            $result = $callback($connection);
            $this->assertTransactionUsable();
            return $result;
        }, $attempts);
    }

    public function commit()
    {
        $this->assertTransactionUsable();
        return parent::commit();
    }

    protected function createTransaction()
    {
        $this->assertTransactionUsable();
        parent::createTransaction();
    }

    private function assertTransactionUsable(): void
    {
        if ($this->abortedTransaction !== null) {
            throw $this->abortedTransaction;
        }
    }

    protected function causedByConcurrencyError(Throwable $e)
    {
        // MariaDB snapshot isolation aborts the entire transaction with ER_CHECKREAD.
        // Treat it like a serialization conflict, not a failed nested savepoint.
        return parent::causedByConcurrencyError($e) || self::isTransactionConflict($e);
    }

    public static function isTransactionConflict(Throwable $e): bool
    {
        return $e instanceof PDOException && (
            (string) $e->getCode() === '40001'
            || in_array((int) ($e->errorInfo[1] ?? 0), [1020, 1205, 1213], true)
        );
    }

    protected function performRollBack($toLevel)
    {
        // The server may already have rolled back (1020 / deadlock). Preserve the
        // original exception and Laravel's transaction bookkeeping/retry budget.
        if ($toLevel === 0 && !$this->getPdo()->inTransaction()) {
            $this->abortedTransaction = null;
            return;
        }

        parent::performRollBack($toLevel);
        if ($toLevel === 0) {
            $this->abortedTransaction = null;
        }
    }
}
