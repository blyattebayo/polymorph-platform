<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Infrastructure\Repositories;

use Polymorph\Platform\Domain\TableConfig\Core\Contracts\TableConfigRepository;
use Polymorph\Platform\Domain\TableConfig\Core\Models\TableConfig;

final class EloquentTableConfigRepository implements TableConfigRepository
{
    private const SCOPE_BASE = 'base';

    private const SCOPE_USER = 'user';

    public function findBase(string $tableKey): ?TableConfig
    {
        return TableConfig::query()
            ->where('table_key', $tableKey)
            ->where('scope', self::SCOPE_BASE)
            ->first();
    }

    public function findUserOverride(string $tableKey, int $userId): ?TableConfig
    {
        return TableConfig::query()
            ->where('table_key', $tableKey)
            ->where('scope', self::SCOPE_USER)
            ->where('user_id', $userId)
            ->first();
    }

    public function upsertBase(string $tableKey, int $schemaVersion, array $configJson): TableConfig
    {
        return TableConfig::query()->updateOrCreate(
            [
                'table_key' => $tableKey,
                'scope' => self::SCOPE_BASE,
            ],
            [
                'user_id' => null,
                'schema_version' => $schemaVersion,
                'config_json' => $configJson,
            ],
        );
    }

    public function upsertUserOverride(string $tableKey, int $userId, int $schemaVersion, array $configJson): TableConfig
    {
        return TableConfig::query()->updateOrCreate(
            [
                'table_key' => $tableKey,
                'scope' => self::SCOPE_USER,
                'user_id' => $userId,
            ],
            [
                'schema_version' => $schemaVersion,
                'config_json' => $configJson,
            ],
        );
    }

    public function delete(TableConfig $config): void
    {
        $config->delete();
    }
}
