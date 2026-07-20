<?php

declare(strict_types=1);

/**
 * Декларативные маршруты для админского API.
 *
 * Собирает стабильные системные группы admin API.
 *
 * @return array<int, array<string, mixed>>
 */
return [
	...require __DIR__ . '/admin/v1.php',
];
