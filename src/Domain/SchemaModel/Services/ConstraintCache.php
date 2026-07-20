<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\FieldRefConstraint;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Кеш для FieldRefConstraints.
 *
 * Предзагружает и кеширует constraints по record_definition_id для оптимизации
 * batch материализации - избегает повторных запросов к БД.
 */
class ConstraintCache
{
    private const CACHE_TTL = 3600; // 1 час

    private const CACHE_PREFIX = 'constraints:record_definition:';

    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Получить constraints для record_definition с кешированием.
     *
     * @return Collection<FieldRefConstraint>
     */
    public function getForRecordDefinition(int $recordDefinitionId): Collection
    {
        $cacheKey = self::CACHE_PREFIX.$recordDefinitionId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($recordDefinitionId) {
            $this->logger->debug('schema.constraint_cache.miss', [
                'record_definition_id' => $recordDefinitionId,
            ]);

            return FieldRefConstraint::query()
                ->where('allowed_record_definition_id', $recordDefinitionId)
                ->get();
        });
    }

    /**
     * Предзагрузить constraints для множества record_definitions.
     *
     * @param  array<int>  $recordDefinitionIds
     * @return array<int, Collection<FieldRefConstraint>> Массив [record_definition_id => constraints]
     */
    public function preloadForRecordDefinitions(array $recordDefinitionIds): array
    {
        if (empty($recordDefinitionIds)) {
            return [];
        }

        $recordDefinitionIds = array_unique($recordDefinitionIds);

        $this->logger->debug('schema.constraint_cache.preload_started', [
            'record_definition_ids' => $recordDefinitionIds,
            'count' => count($recordDefinitionIds),
        ]);

        $result = [];
        $missingIds = [];

        // Проверяем кеш для каждого record_definition
        foreach ($recordDefinitionIds as $recordDefinitionId) {
            $cacheKey = self::CACHE_PREFIX.$recordDefinitionId;
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $result[$recordDefinitionId] = $cached;
            } else {
                $missingIds[] = $recordDefinitionId;
            }
        }

        // Загружаем отсутствующие одним запросом
        if (! empty($missingIds)) {
            $this->logger->debug('schema.constraint_cache.loading_missing', [
                'missing_ids' => $missingIds,
            ]);

            $constraints = FieldRefConstraint::query()
                ->whereIn('allowed_record_definition_id', $missingIds)
                ->get()
                ->groupBy('allowed_record_definition_id');

            foreach ($missingIds as $recordDefinitionId) {
                $recordDefinitionConstraints = $constraints->get($recordDefinitionId, collect());
                $cacheKey = self::CACHE_PREFIX.$recordDefinitionId;

                Cache::put($cacheKey, $recordDefinitionConstraints, self::CACHE_TTL);
                $result[$recordDefinitionId] = $recordDefinitionConstraints;
            }
        }

        $this->logger->debug('schema.constraint_cache.preload_completed', [
            'loaded_from_cache' => count($recordDefinitionIds) - count($missingIds),
            'loaded_from_db' => count($missingIds),
        ]);

        return $result;
    }

    /**
     * Инвалидировать кеш для record_definition.
     *
     * Вызывается при изменении/создании/удалении constraint.
     */
    public function invalidateForRecordDefinition(int $recordDefinitionId): void
    {
        $cacheKey = self::CACHE_PREFIX.$recordDefinitionId;
        Cache::forget($cacheKey);

        $this->logger->debug('schema.constraint_cache.invalidated', [
            'record_definition_id' => $recordDefinitionId,
        ]);
    }

    /**
     * Получить статистику кеша (для мониторинга).
     */
    public function getStats(): array
    {
        // Упрощённая версия, в production нужны метрики из Redis
        return [
            'cache_enabled' => true,
            'ttl' => self::CACHE_TTL,
            'driver' => config('cache.default'),
        ];
    }
}
