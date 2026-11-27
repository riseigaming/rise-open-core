<?php

namespace App\Services\CryptoControl;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

final class CryptoSettlementMaintenanceGate
{
    public const STALE_AFTER_SECONDS = 300;
    private ?string $lease = null;

    private function control(): Connection
    {
        $db = DB::connection();
        if ($db->getDriverName() === 'sqlite' && app()->environment('testing')) {
            return $db;
        }
        if ($db->getDriverName() !== 'mysql') {
            throw new SettlementDeferred('unsupported_coordination_store');
        }
        $name = 'crypto_settlement_control';
        if (!config('database.connections.' . $name)) {
            config(['database.connections.' . $name => $db->getConfig()]);
        }
        $control = DB::connection($name);
        $control->useWriteConnectionWhenReading();
        return $control;
    }

    private function stateRow(Connection $db): object
    {
        $row = $db->table('crypto_settlement_control')->where('id', 1)->lockForUpdate()->first();
        if (!$row || !in_array($row->state, ['RUNNING', 'QUIESCING', 'PAUSED'], true)) {
            throw new SettlementDeferred('control_unavailable');
        }
        return $row;
    }

    public function assertHeld(): void
    {
        if ($this->lease === null || DB::transactionLevel() < 1) {
            throw new SettlementDeferred('mutation_lease_required');
        }
    }

    /** Admission commits before the economic transaction; that transaction locks its lease. */
    public function run(string $source, string $reference, callable $mutation, bool $maintenance = false): mixed
    {
        if ($maintenance && $this->lease !== null) {
            throw new SettlementDeferred('maintenance_requires_paused_drain');
        }
        if ($this->lease !== null) {
            $this->assertHeld();
            return $mutation();
        }
        if (DB::transactionLevel() > 0 && !(app()->environment('testing') && DB::getDriverName() === 'sqlite')) {
            throw new SettlementDeferred('lease_must_precede_transaction');
        }
        if (!preg_match('/\A[a-z0-9_.]{1,64}\z/', $source)) {
            throw new LogicException('Invalid crypto mutation source.');
        }
        $token = (string) Str::uuid();
        $control = $this->control();
        try {
            $control->transaction(function () use ($control, $source, $reference, $token, $maintenance) {
                $row = $this->stateRow($control);
                if ($maintenance) {
                    if ($row->state !== 'PAUSED' || $control->table('crypto_settlement_leases')->exists()) {
                        throw new SettlementDeferred('maintenance_requires_paused_drain');
                    }
                    $control->table('crypto_settlement_control')->where('id', 1)->update(['state' => 'QUIESCING']);
                } elseif ($row->state !== 'RUNNING') {
                    throw new SettlementDeferred('settlement_maintenance');
                }
                $control->table('crypto_settlement_leases')->insert([
                    'id' => $token, 'source' => $source,
                    'reference_hash' => hash_hmac('sha256', $reference, (string) config('app.key')),
                    'started_at' => now()->timestamp,
                ]);
            });
        } catch (SettlementDeferred $exception) {
            Log::notice('Crypto mutation deferred.', ['source' => $source, 'reason' => $exception->reason]);
            throw $exception;
        } catch (\Throwable $exception) {
            throw new SettlementDeferred('control_unavailable');
        }

        try {
            return DB::transaction(function () use ($token, $mutation) {
                // Recovery can delete an abandoned admission, but never this locked row.
                $row = DB::table('crypto_settlement_leases')->where('id', $token)->lockForUpdate()->first();
                if (!$row) {
                    throw new SettlementDeferred('lease_recovered');
                }
                $this->lease = $token;
                $level = DB::transactionLevel();
                $result = $mutation();
                if (DB::transactionLevel() !== $level) {
                    throw new LogicException('Crypto mutation changed its enclosing transaction.');
                }
                DB::table('crypto_settlement_leases')->where('id', $token)->delete();
                return $result;
            }, 1);
        } finally {
            $this->lease = null;
            // Reached only after commit/rollback. If disconnected, leave evidence for recovery.
            try {
                $control->table('crypto_settlement_leases')->where('id', $token)->delete();
                $this->advance();
            } catch (\Throwable $exception) {
                Log::warning('Crypto lease cleanup deferred.', ['exception_class' => get_class($exception)]);
            }
        }
    }

    private function advance(): void
    {
        $db = $this->control();
        $db->transaction(function () use ($db) {
            $row = $this->stateRow($db);
            if ($row->state === 'QUIESCING' && !$db->table('crypto_settlement_leases')->exists()) {
                $db->table('crypto_settlement_control')->where('id', 1)->update(['state' => 'PAUSED']);
            }
        });
    }

    public function status(): array
    {
        $db = $this->control();
        [$row, $leases] = $db->transaction(function () use ($db) {
            // State transitions/admissions cannot race this diagnostic snapshot.
            $row = $this->stateRow($db);
            return [$row, $db->table('crypto_settlement_leases')->orderBy('started_at')->get()];
        });
        $items = $leases->map(fn ($lease) => [
            'lease_id' => $lease->id, 'source' => $lease->source,
            'reference_hash' => $lease->reference_hash,
            'age_seconds' => max(0, now()->timestamp - $lease->started_at),
            'stale' => now()->timestamp - $lease->started_at >= self::STALE_AFTER_SECONDS,
        ])->all();
        $valid = $row->state !== 'PAUSED' || $leases->isEmpty();
        return [
            'state' => $row->state, 'active_mutations' => count($items),
            'oldest_active_age' => $items[0]['age_seconds'] ?? 0,
            'stale_leases' => count(array_filter($items, fn ($item) => $item['stale'])),
            'new_sessions_allowed' => $valid && $row->state === 'RUNNING',
            'settlement_writes_allowed' => $valid && $row->state === 'RUNNING',
            'invariant_valid' => $valid, 'leases' => $items,
        ];
    }

    public function quiesce(int $wait = 0): array
    {
        $db = $this->control();
        $db->transaction(function () use ($db) {
            $this->stateRow($db);
            $db->table('crypto_settlement_control')->where('id', 1)->update(['state' => 'QUIESCING']);
        });
        $deadline = microtime(true) + max(0, min(300, $wait));
        do {
            $this->advance();
            $status = $this->status();
            if ($status['state'] === 'PAUSED' || microtime(true) >= $deadline) {
                return $status;
            }
            usleep(100000);
        } while (true);
    }

    public function resume(): array
    {
        $db = $this->control();
        $db->transaction(function () use ($db) {
            $this->stateRow($db);
            if ($db->table('crypto_settlement_leases')->exists()) {
                throw new SettlementDeferred('drain_incomplete');
            }
            $db->table('crypto_settlement_control')->where('id', 1)->update(['state' => 'RUNNING']);
        });
        return $this->status();
    }

    /** Explicit recovery only. TTL alone NEVER revokes a writer's DB lock. */
    public function recover(): array
    {
        $db = $this->control();
        $ids = $db->table('crypto_settlement_leases')
            ->where('started_at', '<=', now()->timestamp - self::STALE_AFTER_SECONDS)->pluck('id');
        $recovered = 0;
        foreach ($ids as $id) {
            try {
                $recovered += $db->transaction(function () use ($db, $id) {
                    if ($this->stateRow($db)->state !== 'QUIESCING') {
                        throw new SettlementDeferred('recovery_requires_quiescing');
                    }
                    $query = $db->table('crypto_settlement_leases')->where('id', $id);
                    $row = $db->getDriverName() === 'mysql'
                        ? $query->lock('for update nowait')->first()
                        : $query->lockForUpdate()->first();
                    if (!$row || $row->started_at > now()->timestamp - self::STALE_AFTER_SECONDS) {
                        return 0;
                    }
                    // An in-flight economic transaction holds this row until commit/rollback.
                    return $db->table('crypto_settlement_leases')->where('id', $id)->delete();
                });
            } catch (\Illuminate\Database\QueryException $exception) {
                Log::warning('Crypto stale lease remains locked.', ['reason' => 'recovery_deferred']);
            }
        }
        $this->advance();
        if ($recovered > 0) {
            Log::warning('Crypto abandoned leases recovered.', ['recovered_leases' => $recovered]);
        }
        return $this->status() + ['recovered_leases' => $recovered];
    }
}
