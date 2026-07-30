<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\SchemaModel\Access\SchemaFieldResources;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class FieldAccessService
{
    /**
     * @var array<string, list<string>>
     */
    private array $visibleFieldPathsCache = [];

    /**
     * @var array<string, list<string>>
     */
    private array $allowedFieldPathsCache = [];

    /**
     * @var array<int, list<string>>
     */
    private array $schemaFieldPathsCache = [];

    public function __construct(
        private readonly AccessGate $gate,
        private readonly PathTreeFilter $pathTreeFilter,
    ) {}

    public function canAccessField(?UserIdentity $user, int $schemaId, string $action, ?string $fullPath = null): bool
    {
        $fieldPath = trim((string) $fullPath);
        if ($user === null || $schemaId <= 0 || $fieldPath === '' || $action === '') {
            return false;
        }

        return $this->gate->allows($user, $this->resourcePath($schemaId, $fieldPath), trim($action));
    }

    /**
     * @return string[]
     */
    public function visibleFieldPaths(?UserIdentity $user, int $schemaId, string $action = CapabilityCatalog::ACTION_READ): array
    {
        $normalizedAction = trim($action);
        if ($user === null || $schemaId <= 0 || $normalizedAction === '') {
            return [];
        }

        $cacheKey = $user->userId().':'.$schemaId.':'.$normalizedAction;

        return $this->visibleFieldPathsCache[$cacheKey] ??= $this->computeVisibleFieldPaths($user, $schemaId, $normalizedAction);
    }

    /**
     * Доступно ли актору конкретное поле схемы.
     *
     * Отвечает по тому же батчу, что и дерево видимости, поэтому проверка
     * каждого поля схемы по очереди стоит один запрос к гейту, а не N.
     * Fail-closed: нет актора — не видно ничего.
     */
    public function isFieldReadable(?UserIdentity $user, int $schemaId, string $fullPath, string $action = CapabilityCatalog::ACTION_READ): bool
    {
        $normalizedAction = trim($action);
        $fieldPath = trim($fullPath);
        if ($user === null || $schemaId <= 0 || $fieldPath === '' || $normalizedAction === '') {
            return false;
        }

        return in_array($fieldPath, $this->allowedFieldPaths($user, $schemaId, $normalizedAction), true);
    }

    /**
     * Полный список путей полей схемы, без учёта прав.
     *
     * @return list<string>
     */
    public function schemaFieldPaths(int $schemaId): array
    {
        return $this->schemaFieldPathsCache[$schemaId] ??= Field::query()
            ->where('schema_id', $schemaId)
            ->orderByRaw('LENGTH(full_path), full_path')
            ->pluck('full_path')
            ->map(static fn (mixed $path): string => trim((string) $path))
            ->filter(static fn (string $path): bool => $path !== '')
            ->values()
            ->all();
    }

    /**
     * @return string[]
     */
    private function computeVisibleFieldPaths(UserIdentity $user, int $schemaId, string $action): array
    {
        $visible = $this->allowedFieldPaths($user, $schemaId, $action);

        return array_values(array_filter(
            $visible,
            static fn (string $path): bool => ! self::hasVisibleDescendant($path, $visible)
        ));
    }

    /**
     * Разрешённые актору пути БЕЗ схлопывания поддеревьев: на этом списке
     * отвечает isFieldReadable(), а после схлопывания — дерево видимости.
     *
     * @return list<string>
     */
    private function allowedFieldPaths(UserIdentity $user, int $schemaId, string $action): array
    {
        $cacheKey = $user->userId().':'.$schemaId.':'.$action;

        return $this->allowedFieldPathsCache[$cacheKey] ??= (function () use ($user, $schemaId, $action): array {
            $fieldPaths = $this->schemaFieldPaths($schemaId);

            if ($fieldPaths === []) {
                return [];
            }

            $checks = array_map(
                fn (string $path): AccessCheck => new AccessCheck($this->resourcePath($schemaId, $path), $action),
                $fieldPaths,
            );
            $allowed = $this->gate->allowsEach($user, $checks);

            $result = [];
            foreach ($fieldPaths as $index => $path) {
                if ($allowed[$index] ?? false) {
                    $result[] = $path;
                }
            }

            return $result;
        })();
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function filterReadableDataJson(?UserIdentity $user, int $schemaId, array $dataJson): array
    {
        return $this->pathTreeFilter->filterVisibleData($dataJson, $this->visibleFieldPaths($user, $schemaId, CapabilityCatalog::ACTION_READ));
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return string[]
     */
    public function forbiddenWritePaths(?UserIdentity $user, int $schemaId, array $dataJson): array
    {
        return $this->pathTreeFilter->forbiddenWritePaths($dataJson, $this->visibleFieldPaths($user, $schemaId, CapabilityCatalog::ACTION_WRITE));
    }

    private function resourcePath(int $schemaId, string $fieldPath): ResourceRef
    {
        return SchemaFieldResources::field($schemaId, ltrim($fieldPath, '.'));
    }

    /**
     * @param  list<string>  $visiblePaths
     */
    private static function hasVisibleDescendant(string $path, array $visiblePaths): bool
    {
        foreach ($visiblePaths as $candidate) {
            if ($candidate !== $path && str_starts_with($candidate, $path.'.')) {
                return true;
            }
        }

        return false;
    }
}
