<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class FieldAccessService
{
    /**
     * @var array<string, list<string>>
     */
    private array $visibleFieldPathsCache = [];

    /**
     * @var array<int, list<string>>
     */
    private array $schemaFieldPathsCache = [];

    public function __construct(
        private readonly PolicyRuntime $runtime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly PathTreeFilter $pathTreeFilter,
    ) {}

    public function canAccessField(?UserIdentity $user, int $schemaId, string $action, ?string $fullPath = null): bool
    {
        $fieldPath = trim((string) $fullPath);
        if ($user === null || $schemaId <= 0 || $fieldPath === '' || $action === '') {
            return false;
        }

        return $this->runtime->allows(
            $this->subjectProvider->for($user),
            $this->resourcePath($schemaId, $fieldPath),
            trim($action),
        );
    }

    /**
     * @return string[]
     */
    public function visibleFieldPaths(?UserIdentity $user, int $schemaId, string $action = 'read'): array
    {
        $normalizedAction = trim($action);
        if ($user === null || $schemaId <= 0 || $normalizedAction === '') {
            return [];
        }

        $cacheKey = $user->userId().':'.$schemaId.':'.$normalizedAction;

        return $this->visibleFieldPathsCache[$cacheKey] ??= $this->computeVisibleFieldPaths($user, $schemaId, $normalizedAction);
    }

    /**
     * @return string[]
     */
    private function computeVisibleFieldPaths(UserIdentity $user, int $schemaId, string $action): array
    {
        $fieldPaths = $this->schemaFieldPathsCache[$schemaId] ??= Field::query()
            ->where('schema_id', $schemaId)
            ->orderByRaw('LENGTH(full_path), full_path')
            ->pluck('full_path')
            ->map(static fn (mixed $path): string => trim((string) $path))
            ->filter(static fn (string $path): bool => $path !== '')
            ->values()
            ->all();

        if ($fieldPaths === []) {
            return [];
        }

        $checks = array_map(
            fn (string $path): array => ['resource' => $this->resourcePath($schemaId, $path), 'action' => $action],
            $fieldPaths,
        );
        $decisions = $this->runtime->batchEvaluate($this->subjectProvider->for($user), $checks);

        $visible = [];
        foreach ($fieldPaths as $index => $path) {
            $decision = $decisions[$index] ?? null;
            if ($decision !== null && $decision->allowed()) {
                $visible[] = $path;
            }
        }

        return array_values(array_filter(
            $visible,
            static fn (string $path): bool => ! self::hasVisibleDescendant($path, $visible)
        ));
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public function filterReadableDataJson(?UserIdentity $user, int $schemaId, array $dataJson): array
    {
        return $this->pathTreeFilter->filterVisibleData($dataJson, $this->visibleFieldPaths($user, $schemaId, 'read'));
    }

    /**
     * @param  array<string, mixed>  $dataJson
     * @return string[]
     */
    public function forbiddenWritePaths(?UserIdentity $user, int $schemaId, array $dataJson): array
    {
        return $this->pathTreeFilter->forbiddenWritePaths($dataJson, $this->visibleFieldPaths($user, $schemaId, 'write'));
    }

    private function resourcePath(int $schemaId, string $fieldPath): string
    {
        return 'schema.'.$schemaId.'.fields.'.ltrim($fieldPath, '.');
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
