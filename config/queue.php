<?php

return [

    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            // Must stay LONGER than the worker's --timeout (docker/entrypoint.sh),
            // or the queue releases a job back for another worker while the
            // first is still running it. The default 90s was fine when nothing
            // was queued; the admin Data Updater now enqueues commands that loop
            // the whole exchange and run for many minutes, so at 90s each one
            // would be picked up again mid-flight, run concurrently against the
            // same tables, and eventually land in failed_jobs.
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 1860),
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
