<?php

return [
    // A real database queue quarantines work until a separately authorized worker exists.
    'default' => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'database' => ['driver' => 'database', 'table' => 'jobs_table', 'queue' => 'default', 'retry_after' => 90],
    ],
    'failed' => ['driver' => 'database', 'database' => env('DB_CONNECTION', 'mysql'), 'table' => 'failed_jobs'],
];
