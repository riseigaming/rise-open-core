<?php

$environment = env('APP_ENV', 'local');
$enabled = in_array($environment, ['local', 'testing', 'candidate', 'preview'], true);

return [
    'enabled' => $enabled,
    'allowed_connections' => ['mysql'],
    'allowed_local_hosts' => ['127.0.0.1', 'localhost', '::1'],
    'allowed_databases' => ['rise_b1_candidate', 'rise_b1_test'],
    'allowed_test_databases' => ['rise_b1_test'],
    'observe_commands' => [
        'app:admin-bootstrap',
        'app:db:*',
        'migrate',
        'migrate:*',
        'db:*',
        'queue:*',
        'test',
        'horizon',
        'schedule:*',
    ],
    'destructive_commands' => [
        'app:admin-bootstrap',
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:seed',
        'db:wipe',
    ],
    'guard_destructive_commands' => $enabled,
    'require_recent_snapshot' => $enabled && (bool) env('DB_SAFETY_REQUIRE_RECENT_SNAPSHOT', true),
    'allow_unguarded_destructive' => false,
    'snapshot' => [
        'directory' => base_path(trim((string) env('DB_SAFETY_SNAPSHOT_DIRECTORY', 'DB_BACKUPS'), '/')),
        'recent_minutes' => max(1, (int) env('DB_SAFETY_RECENT_SNAPSHOT_MINUTES', 1440)),
        'extensions' => ['sql', 'sql.gz', 'zip'],
    ],
];
