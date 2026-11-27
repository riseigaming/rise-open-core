<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\{InputInterface, InputDefinition, InputArgument, InputOption};
use RuntimeException;

/** Narrow reviewed-source admission, never a general migration classifier. */
class AdditiveMigrationSafety
{
    public function contracts(): array
    {
        return require base_path('database/safety/additive.php');
    }

    public function approved(InputInterface $input): bool
    {
        // CommandStarting precedes Symfony binding. Bind a CLONE with array path
        // semantics: repeated --path must not hide another migration. Reject
        // --database, --realpath, --seed, --schema-path and all unknown options.
        $copy = clone $input;
        try {
            $copy->bind(new InputDefinition([
                new InputArgument('command', InputArgument::REQUIRED),
                new InputOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
                new InputOption('force'), new InputOption('no-interaction', 'n'),
            ]));
            $paths = $copy->getOption('path');
        } catch (\Throwable) { return false; }
        if ($copy->getFirstArgument() !== 'migrate' || count($paths) !== 1) return false;
        $path = $paths[0]; $contract = $this->contracts()[$path] ?? null;
        if (!$contract) return false;
        if (realpath(base_path($path)) !== base_path($path)) return false;
        if (config('database.connections.'.config('database.default').'.driver') !== 'mysql') return false;
        if (config('database.connections.'.config('database.default').'.prefix', '') !== '') return false;
        // Hash before reading executable migration code; no eval/require/pretend.
        if (!hash_equals($contract['sha256'], hash_file('sha256', base_path($path)))) {
            throw new RuntimeException('[db-safety] Additive source identity changed; re-review required.');
        }
        if (!hash_equals($contract['feature_sha256'], hash_file('sha256', base_path($contract['feature_source'])))
            || config($contract['feature']) !== false) {
            throw new RuntimeException('[db-safety] Additive feature default/runtime must remain OFF.');
        }
        $this->validate($contract, $this->inspect($contract, pathinfo($path, PATHINFO_FILENAME)));
        return true;
    }

    /** Read-only schema state; overridable only for deterministic policy tests. */
    protected function inspect(array $contract, string $migration): array
    {
        $db = DB::connection(); $schema = $db->getSchemaBuilder();
        $ledger = (string) config('database.migrations', 'migrations');
        if (!$schema->hasTable($ledger) || !$db->table($ledger)->exists()) {
            throw new RuntimeException('[db-safety] Existing nonempty migration ledger required (schema loading forbidden).');
        }
        $tables = [];
        foreach (array_unique(array_merge(array_keys($contract['tables']), array_keys($contract['requires']))) as $table) {
            $tables[$table] = $this->describe($table);
        }
        return ['applied' => $db->table($ledger)->where('migration', $migration)->count(), 'tables' => $tables];
    }

    public function describe(string $table): ?array
    {
        $db = DB::connection(); $name = $db->getDatabaseName();
        $meta = $db->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$name, $table]);
        if (!$meta) return null;
        $columns = []; $auto = [];
        $checks = $db->select('SELECT c.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t ON t.CONSTRAINT_SCHEMA=c.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME=c.CONSTRAINT_NAME WHERE t.TABLE_SCHEMA = ? AND t.TABLE_NAME = ?', [$name, $table]);
        $jsonChecks = array_map(fn ($r) => strtolower(trim($r->CHECK_CLAUSE)), $checks);
        foreach ($db->select('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$name, $table]) as $c) {
            $type = preg_replace('/\b(bigint|int|tinyint)\(\d+\)/', '$1', strtolower($c->COLUMN_TYPE));
            if ($type === 'longtext' && in_array('json_valid(`'.$c->COLUMN_NAME.'`)', $jsonChecks, true)) $type = 'json';
            $default = $c->COLUMN_DEFAULT;
            if ($default === 'NULL') $default = null;
            elseif (is_string($default) && str_starts_with($default, "'") && str_ends_with($default, "'")) $default = substr($default, 1, -1);
            $columns[$c->COLUMN_NAME] = [$type, $c->IS_NULLABLE === 'YES', $default];
            if (str_contains($c->EXTRA, 'auto_increment')) $auto[] = $c->COLUMN_NAME;
        }
        $indexes = [];
        foreach ($db->select('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$name, $table]) as $i) {
            $indexes[$i->INDEX_NAME][0] = !$i->NON_UNIQUE; $indexes[$i->INDEX_NAME][1][] = $i->COLUMN_NAME;
            // A prefix/hash/fulltext index is not the reviewed full BTREE key.
            if ($i->SUB_PART !== null || $i->INDEX_TYPE !== 'BTREE') $indexes[$i->INDEX_NAME][2][] = [$i->SUB_PART, $i->INDEX_TYPE];
        }
        $foreign = [];
        foreach ($db->select('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$name, $table]) as $f) {
            $foreign[$f->COLUMN_NAME] = [$f->REFERENCED_TABLE_NAME, $f->REFERENCED_COLUMN_NAME, $f->DELETE_RULE, $f->UPDATE_RULE];
        }
        return ['engine' => $meta->ENGINE, 'columns' => $columns, 'indexes' => $indexes, 'foreign' => $foreign, 'auto_increment' => $auto];
    }

    public function validate(array $contract, array $observed): void
    {
        $fail = static function (string $reason): void { throw new RuntimeException('[db-safety] Additive preflight failed: '.$reason); };
        if (($contract['class'] ?? '') !== 'new-tables-only-v1') $fail('unreviewed safety class');
        if (!in_array($observed['applied'], [0, 1], true)) $fail('migration ledger ambiguity');
        foreach ($contract['requires'] as $table => $columns) {
            $actual = $observed['tables'][$table] ?? null;
            if (!$actual || $actual['engine'] !== 'InnoDB') $fail('required parent/schema missing: '.$table);
            foreach ($columns as $column) if (!isset($actual['columns'][$column])) $fail('required column missing: '.$table.'.'.$column);
            foreach ($contract['required_types'][$table] ?? [] as $column => $type) {
                if (($actual['columns'][$column][0] ?? null) !== $type) $fail('required type mismatch: '.$table.'.'.$column);
            }
            if (($actual['indexes']['PRIMARY'] ?? null) !== [true, ['id']]) $fail('required primary key missing: '.$table);
        }
        foreach ($contract['tables'] as $table => $expected) {
            $actual = $observed['tables'][$table] ?? null;
            if ($observed['applied'] === 0 && $actual !== null) $fail('target collision/partial DDL: '.$table);
            if ($observed['applied'] === 1) {
                if (!$actual || $actual['engine'] !== 'InnoDB') $fail('applied table missing: '.$table);
                foreach (['columns', 'indexes', 'foreign', 'auto_increment'] as $key) {
                    $found = $actual[$key] ?? []; $wanted = $expected[$key] ?? [];
                    // This reviewed class creates InnoDB tables only. InnoDB
                    // checks NO ACTION immediately, exactly like RESTRICT. Do
                    // not extend this equivalence to NDB/deferred constraints.
                    if ($key === 'foreign' && $actual['engine'] === 'InnoDB') {
                        $normalize = static function (array $foreign): array {
                            foreach ($foreign as &$fk) foreach ([2, 3] as $action)
                                if (($fk[$action] ?? null) === 'NO ACTION') $fk[$action] = 'RESTRICT';
                            return $foreign;
                        };
                        $found = $normalize($found); $wanted = $normalize($wanted);
                    }
                    if ($found != $wanted) $fail('applied schema drift: '.$table.'.'.$key);
                }
            }
            // A new parent must precede the child and expose the matching unique key.
            foreach ($expected['foreign'] as $column => [$parent, $parentColumn]) {
                $p = $contract['tables'][$parent] ?? ($observed['tables'][$parent] ?? null);
                if (!$p || !isset($p['columns'][$parentColumn]) || $p['columns'][$parentColumn][0] !== $expected['columns'][$column][0]) $fail('FK parent/type missing');
                if (!in_array([true, [$parentColumn]], array_values($p['indexes']), true)) $fail('FK parent unique key missing');
                if (isset($contract['tables'][$parent]) && array_search($parent, array_keys($contract['tables']), true) >= array_search($table, array_keys($contract['tables']), true)) $fail('FK parent creation order');
            }
        }
    }
}
