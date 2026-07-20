<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;

/**
 * Сервис для вычисления материализованных путей полей.
 * 
 * Отвечает за построение full_path при создании и перемещении полей.
 */
class FieldPathCalculator
{
    public function __construct(
        private readonly FieldRepository $repository,
    ) {
    }

    /**
     * Вычислить полный путь для поля.
     */
    public function calculatePath(?Field $parent, string $name): FieldPath
    {
        if ($parent === null) {
            return FieldPath::fromString($name);
        }

        return FieldPath::fromNameAndParent($name, $parent->getPathObject());
    }

    /**
     * Вычислить новые пути при изменении родителя.
     * 
     * @return array{field: FieldPath, descendants: array<int, FieldPath>}
     */
    public function recalculatePathsOnParentChange(
        Field $field,
        ?Field $newParent
    ): array {
        $oldPath = $field->getPathObject();
        $newPath = $this->calculatePath($newParent, $field->name);

        // Получить всех потомков
        $descendants = $this->repository->getAllDescendants($field);

        $descendantPaths = [];
        foreach ($descendants as $descendant) {
            $descendantPath = $descendant->getPathObject();
            $relativePath = $this->getRelativePath($oldPath, $descendantPath);
            $descendantPaths[$descendant->id] = FieldPath::fromString(
                $newPath->toString() . '.' . $relativePath
            );
        }

        return [
            'field' => $newPath,
            'descendants' => $descendantPaths,
        ];
    }

    /**
     * Получить относительный путь между двумя путями.
     */
    private function getRelativePath(FieldPath $base, FieldPath $full): string
    {
        $baseSegments = $base->segments();
        $fullSegments = $full->segments();

        return implode('.', array_slice($fullSegments, count($baseSegments)));
    }

    /**
     * Проверить, не создаст ли перемещение циклическую зависимость.
     */
    public function wouldCreateCircularDependency(Field $field, ?Field $newParent): bool
    {
        if ($newParent === null) {
            return false;
        }

        // Нельзя переместить в себя
        if ($newParent->id === $field->id) {
            return true;
        }

        // Нельзя переместить в свой потомок
        $parentPath = $newParent->getPathObject();
        $fieldPath = $field->getPathObject();

        return $parentPath->isChildOf($fieldPath);
    }
}
