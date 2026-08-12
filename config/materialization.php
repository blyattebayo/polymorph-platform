<?php

return [
    'enabled' => (bool) env('MATERIALIZATION_ENABLED', true),
    'connection' => env('MATERIALIZATION_DB_CONNECTION', null),

    'queue' => [
        'name' => env('MATERIALIZATION_QUEUE', 'materialization'),
        'batch_size' => (int) env('MATERIALIZATION_BATCH_SIZE', 100),
        'max_batches_per_job' => (int) env('MATERIALIZATION_MAX_BATCHES_PER_JOB', 20),
        'retry_after' => (int) env('MATERIALIZATION_RETRY_AFTER', 90),
        'max_attempts' => (int) env('MATERIALIZATION_MAX_ATTEMPTS', 5),
        'base_backoff_seconds' => (int) env('MATERIALIZATION_BASE_BACKOFF_SECONDS', 60),
    ],

    'telemetry' => [
        'enabled' => (bool) env('MATERIALIZATION_TELEMETRY_ENABLED', true),
    ],

    'media_index' => [
        'enabled' => (bool) env('MATERIALIZATION_MEDIA_INDEX_ENABLED', true),
    ],

    'schema_cache' => [
        'enabled' => (bool) env('MATERIALIZATION_SCHEMA_CACHE_ENABLED', false),
        'store' => env('MATERIALIZATION_SCHEMA_CACHE_STORE', null),
        'ttl_seconds' => (int) env('MATERIALIZATION_SCHEMA_CACHE_TTL_SECONDS', 300),
        'prefix' => env('MATERIALIZATION_SCHEMA_CACHE_PREFIX', 'materialization:schema:'),
    ],

];
