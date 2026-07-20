<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Core\Contracts;

use Polymorph\Platform\Domain\TableConfig\Core\Models\TableConfig;

/**
 * Персистентный слой для конфигов таблиц (base + per-user overrides).
 */
interface TableConfigRepository
{
    public function findBase(string $tableKey): ?TableConfig;

    public function findUserOverride(string $tableKey, int $userId): ?TableConfig;

    /**
     * @param  array<string, mixed>  $configJson
     */
    public function upsertBase(string $tableKey, int $schemaVersion, array $configJson): TableConfig;

    /**
     * @param  array<string, mixed>  $configJson
     */
    public function upsertUserOverride(string $tableKey, int $userId, int $schemaVersion, array $configJson): TableConfig;

    public function delete(TableConfig $config): void;
}
