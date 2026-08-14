<?php

declare(strict_types=1);

return [
    'query' => [
        'unindexed_policy' => env('DATA_PLATFORM_UNINDEXED_QUERY_POLICY', env('APP_ENV') === 'production' ? 'fail' : 'warn'),
        'allow_scan_requests' => (bool) env('DATA_PLATFORM_ALLOW_SCAN_REQUESTS', false),
        'max_acl_fallback_candidates' => (int) env('DATA_PLATFORM_MAX_ACL_FALLBACK_CANDIDATES', 10000),
    ],
    'hydration' => [
        'max_depth' => (int) env('DATA_PLATFORM_MAX_HYDRATION_DEPTH', 3),
    ],
    'display' => [
        'max_ref_depth' => (int) env('DATA_PLATFORM_MAX_DISPLAY_REF_DEPTH', 3),
        'rebuild_batch_size' => (int) env('DATA_PLATFORM_DISPLAY_REBUILD_BATCH_SIZE', 200),
    ],
    'delete' => [
        'max_cascade_depth' => (int) env('DATA_PLATFORM_MAX_DELETE_CASCADE_DEPTH', 100),
    ],
    'projection' => [
        'max_error_length' => (int) env('DATA_PLATFORM_PROJECTION_MAX_ERROR_LENGTH', 4000),
    ],
    'migration' => [
        'max_record_attempts' => (int) env('DATA_PLATFORM_MIGRATION_MAX_RECORD_ATTEMPTS', 3),
    ],
    'outbox' => [
        'batch_size' => (int) env('DATA_PLATFORM_OUTBOX_BATCH_SIZE', 100),
        'lock_timeout_seconds' => (int) env('DATA_PLATFORM_OUTBOX_LOCK_TIMEOUT_SECONDS', 300),
        'backoff_base_seconds' => (int) env('DATA_PLATFORM_OUTBOX_BACKOFF_BASE_SECONDS', 2),
        'max_backoff_exponent' => (int) env('DATA_PLATFORM_OUTBOX_MAX_BACKOFF_EXPONENT', 10),
        'max_backoff_seconds' => (int) env('DATA_PLATFORM_OUTBOX_MAX_BACKOFF_SECONDS', 3600),
        'max_attempts' => (int) env('DATA_PLATFORM_OUTBOX_MAX_ATTEMPTS', 10),
        'max_error_length' => (int) env('DATA_PLATFORM_OUTBOX_MAX_ERROR_LENGTH', 4000),
    ],
];
