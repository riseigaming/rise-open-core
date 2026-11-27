<?php

namespace App\Console\Commands;

use App\Models\CmsUser;
use App\Support\CmsThemeResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Throwable;

class CheckFreshInstallationCommand extends Command
{
    protected $signature = 'app:install:check';
    protected $description = 'Read-only checks for a fresh Rise Open Core installation.';
    private bool $failed = false;

    public function handle(): int
    {
        $contract = require base_path('scripts/b1-boot-contract.php');
        $this->check('PHP >= '.$contract['php_minimum'], version_compare(PHP_VERSION, $contract['php_minimum'], '>='));
        foreach ($contract['required_extensions'] as $extension) {
            $this->check('extension '.$extension, extension_loaded($extension));
        }
        $this->check('APP_KEY present', trim((string) config('app.key')) !== '');
        $configurationValid = $this->checkConfiguration();

        $expectedMigrations = $contract['migration_names'];
        $actualMigrations = array_map(static fn ($path) => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);
        sort($expectedMigrations);
        sort($actualMigrations);
        $this->check('exact '.count($expectedMigrations).' migration files', $actualMigrations === $expectedMigrations);

        if ($configurationValid) {
            try {
                $this->check('database identity', (string) (DB::selectOne('SELECT DATABASE() AS name')->name ?? '') === config('database.connections.mysql.database'));
                $tablesAvailable = true;
                foreach ($contract['required_tables'] as $table) {
                    $exists = Schema::hasTable($table);
                    $this->check('table '.$table, $exists);
                    $tablesAvailable = $tablesAvailable && $exists;
                    foreach ($contract['required_columns'][$table] ?? [] as $column) {
                        $this->check('column '.$table.'.'.$column, $exists && Schema::hasColumn($table, $column));
                    }
                }
                if ($tablesAvailable) {
                    $ledger = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
                    $this->check('exact '.count($expectedMigrations).' migration ledger', $ledger === $expectedMigrations);
                    $this->checkSchema($contract);
                    $this->checkBaseline();
                }
            } catch (Throwable $exception) {
                $this->check('database/schema inspection ('.class_basename($exception).')', false);
            }
        } else {
            $this->check('database checks blocked by invalid candidate configuration', false);
        }

        $this->checkRegistry($contract);
        foreach ($contract['views'] as $view) {
            $this->check('view '.$view['view_name'], is_file(base_path($view['path'])) && View::exists($view['view_name']));
        }
        foreach ($contract['writable_directories'] as $directory) {
            $this->check('writable '.$directory, is_dir(base_path($directory)) && is_writable(base_path($directory)));
        }
        $this->line('Scope: diagnostic metadata checks only; no migrations, seeds, repairs, rendering, HTTP, mail or provider requests.');
        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok): bool
    {
        $this->line(($ok ? 'PASS ' : 'FAIL ').$label);
        $this->failed = $this->failed || !$ok;
        return $ok;
    }

    private function checkConfiguration(): bool
    {
        $checks = [
            'CMS guard and password broker' => config('auth.defaults.guard') === 'cms'
                && config('auth.defaults.passwords') === 'cms_users'
                && config('auth.guards.cms.driver') === 'session'
                && config('auth.guards.cms.provider') === 'cms'
                && config('auth.providers.cms.model') === CmsUser::class
                && config('auth.passwords.cms_users.provider') === 'cms'
                && array_keys(config('auth.guards', [])) === ['cms'],
            'file session' => config('session.driver') === 'file',
            'file cache' => config('cache.default') === 'file',
            'database queue quarantine' => config('queue.default') === 'database'
                && config('queue.connections.database.table') === 'jobs_table'
                && config('queue.failed.driver') === 'database'
                && config('queue.failed.table') === 'failed_jobs',
            'local mail capture' => config('mail.default') === 'log' && config('local_safety.block_mail_delivery') === true,
            'local upload disks' => array_keys(config('filesystems.disks', [])) === ['local', 'public']
                && in_array(config('filesystems.default'), ['local', 'public'], true)
                && config('filesystems.disks.local.driver') === 'local'
                && config('filesystems.disks.public.driver') === 'local',
            'local logs' => in_array(config('logging.default'), ['daily', 'single', 'stack', 'local_safety', 'stderr', 'errorlog'], true)
                && array_diff(array_keys(config('logging.channels', [])), ['daily', 'single', 'stack', 'local_safety', 'stderr', 'errorlog', 'emergency', 'account_mail']) === [],
            'private local safety' => in_array(app()->environment(), ['local', 'testing', 'candidate', 'preview'], true)
                && config('local_safety.enabled') === true && config('local_safety.block_external_requests') === true
                && config('local_safety.allowed_url_authorities') === [] && config('restrictions.geo.enabled') === false && config('restrictions.geo.allowed_authorities') === [],
            'explicit isolated MySQL identity' => config('database.default') === 'mysql'
                && in_array(config('database.connections.mysql.database'), ['rise_b1_candidate', 'rise_b1_test'], true)
                && in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1', '::1'], true)
                && trim((string) config('database.connections.mysql.username')) !== '',
            'initial locale and timezone configuration' => config('app.locale') === 'en'
                && config('app.fallback_locale') === 'en' && config('app.timezone') === 'UTC',
            'Global physical namespace' => CmsThemeResolver::activePhysicalNamespace() === 'global',
        ];
        foreach ($checks as $label => $ok) {
            $this->check('configuration '.$label, $ok);
        }
        return !in_array(false, $checks, true);
    }

    private function checkSchema(array $contract): void
    {
        $columns = collect(DB::select('SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, EXTRA FROM information_schema.columns WHERE table_schema = DATABASE()'))
            ->keyBy(static fn ($row) => $row->TABLE_NAME.'.'.$row->COLUMN_NAME);
        foreach ($contract['column_types'] as $table => $specs) {
            foreach ($specs as $column => $expected) {
                $actual = $columns->get($table.'.'.$column);
                $types = $expected['type'] === 'json' ? ['json', 'longtext'] : ($expected['type'] === 'double' ? ['float', 'double'] : [$expected['type']]);
                $ok = $actual && in_array(strtolower($actual->DATA_TYPE), $types, true);
                if (array_key_exists('nullable', $expected)) $ok = $ok && (($actual->IS_NULLABLE ?? '') === ($expected['nullable'] ? 'YES' : 'NO'));
                if (isset($expected['unsigned'])) $ok = $ok && str_contains(strtolower((string) ($actual->COLUMN_TYPE ?? '')), 'unsigned');
                if (isset($expected['length'])) $ok = $ok && (int) ($actual->CHARACTER_MAXIMUM_LENGTH ?? 0) === $expected['length'];
                if (isset($expected['generated'])) $ok = $ok && str_contains(strtolower((string) ($actual->EXTRA ?? '')), 'stored generated');
                $this->check('schema type '.$table.'.'.$column, (bool) $ok);
            }
        }
        $foreignKeys = DB::select('SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL');
        foreach ($contract['foreign_keys'] as $expected) {
            foreach ($expected['columns'] as $offset => $column) {
                $matches = array_filter($foreignKeys, static fn ($fk) => $fk->TABLE_NAME === $expected['table'] && $fk->COLUMN_NAME === $column
                    && $fk->REFERENCED_TABLE_NAME === $expected['references']['table']
                    && $fk->REFERENCED_COLUMN_NAME === $expected['references']['columns'][$offset]
                    && ($expected['on_delete'] !== 'cascade' || $fk->DELETE_RULE === 'CASCADE'));
                $this->check('foreign key '.$expected['table'].'.'.$column, $matches !== []);
            }
        }
        $indexes = collect(DB::select('SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE FROM information_schema.statistics WHERE table_schema=DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'))
            ->groupBy(static fn ($row) => $row->TABLE_NAME.'.'.$row->INDEX_NAME);
        foreach ($contract['indexes'] as $expected) {
            $found = $indexes->contains(static function ($rows) use ($expected): bool {
                if ($rows->first()->TABLE_NAME !== $expected['table']) return false;
                if (in_array($expected['kind'], ['unique', 'primary'], true) && (int) $rows->first()->NON_UNIQUE !== 0) return false;
                if ($expected['kind'] === 'primary' && $rows->first()->INDEX_NAME !== 'PRIMARY') return false;
                $actual = $rows->pluck('COLUMN_NAME')->all();
                return $expected['kind'] === 'foreign_key_required_index'
                    ? array_slice($actual, 0, count($expected['columns'])) === $expected['columns'] : $actual === $expected['columns'];
            });
            $this->check('index '.$expected['table'].'('.implode(',', $expected['columns']).')', $found);
        }
    }

    private function checkBaseline(): void
    {
        $languages = DB::table('cms_languages')->where('status', 1)->get();
        $this->check('active en/es/tr languages', $languages->pluck('code')->sort()->values()->all() === ['en', 'es', 'tr']);
        $this->check('single active en default', $languages->where('is_default', 1)->pluck('code')->values()->all() === ['en']);
        $settings = DB::table('cms_site_settings')->get();
        $setting = $settings->first();
        $this->check('single neutral Global base setting', $settings->count() === 1 && $setting->active_theme === 'global'
            && $setting->default_lang === 'en' && (int) $setting->register_status === 0 && $setting->account_mail_status === 'active');
        foreach ($languages as $language) {
            $translation = $setting ? DB::table('cms_site_setting_translations')->where('cms_setting_id', $setting->id)->where('cms_language_id', $language->id)->first() : null;
            $this->check('generic site title '.$language->code, $translation && in_array($translation->site_title, ['New CMS', 'Rise Open Core'], true));
            $this->check('translation directory '.$language->code, is_dir(resource_path('lang/'.$language->code)));
        }
        $types = DB::table('cms_user_types')->whereNull('deleted_at')->pluck('id', 'name');
        $this->check('core user type catalog', count(array_intersect(['Normal', 'VIP', 'Riskli'], $types->keys()->all())) === 3);
        $countries = DB::table('cms_countries')->get();
        $this->check('explicit unique ISO2 countries', $countries->isNotEmpty() && $countries->every(static fn ($country) => preg_match('/^[A-Z]{2}$/', (string) $country->iso2) && trim($country->name) !== '')
            && $countries->pluck('iso2')->unique()->count() === $countries->count());
        $currencies = DB::table('cms_currencies')->where('status', 'active')->whereNull('deleted_at')->whereIn('code', config('product_wallets.fiat_assets', []))->pluck('code')->all();
        $this->check('active supported fiat metadata', $currencies !== []);
        $admins = DB::table('cms_users')->where('role', 'admin')->where('status', 'normal')->whereNull('deleted_at')->get();
        $validAdmin = $admins->contains(static fn ($user) => in_array($user->country_id, $countries->pluck('id')->all())
            && in_array($user->user_type_id, $types->all()) && $user->language === 'en'
            && in_array($user->currency, $currencies, true) && ($user->password_raw === null)
            && (password_get_info((string) $user->password)['algo'] ?? null) !== null);
        $this->check('first CMS admin identity and password hash', $validAdmin);
        $this->check('bootstrap password mutation audit', DB::table('cms_sensitive_mutation_events')->where('source', 'app:admin-bootstrap')->exists());
    }

    private function checkRegistry(array $contract): void
    {
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $isRedirect = in_array(ltrim($route->getActionName(), '\\'), [
                'Illuminate\\Routing\\RedirectController',
                'Illuminate\\Routing\\RedirectController@__invoke',
            ], true);
            if ($isRedirect) {
                $this->check('selected redirect destination', ($route->defaults['destination'] ?? null) === '/'
                    && ($route->defaults['status'] ?? null) === 302);
            }
            $method = $isRedirect ? 'REDIRECT' : implode('|', array_values(array_diff($route->methods(), ['HEAD'])));
            $middlewares = array_map(static fn ($name) => str_starts_with($name, 'App\\Http\\Middleware\\') ? class_basename($name) : $name, $route->middleware());
            $key = $method.' /'.ltrim($route->uri(), '/');
            $actual[$key] = ['name' => $route->getName(), 'middleware' => $middlewares, 'controller' => $isRedirect ? null : $route->getActionName()];
        }
        $this->check('exact HTTP registry count', count($actual) === count($contract['routes']));
        foreach ($contract['routes'] as $expected) {
            $key = $expected['method'].' '.$expected['uri'];
            $this->check('route '.$expected['id'], ($actual[$key] ?? null) === [
                'name' => $expected['name'], 'middleware' => $expected['middleware'], 'controller' => $expected['controller'],
            ]);
        }
        foreach (app('router')->getMiddleware() as $alias => $class) {
            $this->check('middleware '.$alias, class_exists($class));
        }
    }
}
