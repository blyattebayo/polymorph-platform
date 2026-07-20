<?php

declare(strict_types=1);

return [
    /*
    | Rollback: set PAT_ENABLED=false to reject PAT authentication while keeping
    | JWT sessions. Set PAT_CREATION_ENABLED=false to hide/disable issuance while
    | revocation and admin listing remain available for cleanup.
    */
    'enabled' => filter_var(env('PAT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'creation_enabled' => filter_var(env('PAT_CREATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'prefix' => env('PAT_PREFIX', 'pmph_pat_'),

    'default_ttl' => env('PAT_DEFAULT_TTL', 'P90D'),

    'ttl_options' => [
        'P30D',
        'P90D',
        'P180D',
        'P365D',
    ],
];
