<?php

declare(strict_types=1);

return [
    'query' => [
        // Логировать предупреждение при фильтрации/сортировке по неиндексированному полю.
        'warn_unindexed' => (bool) env('RECORDS_QUERY_WARN_UNINDEXED', true),

        // Запрещать фильтрацию/сортировку по неиндексированным полям (бросать исключение).
        'strict_indexed' => (bool) env('RECORDS_QUERY_STRICT_INDEXED', false),
    ],
];
