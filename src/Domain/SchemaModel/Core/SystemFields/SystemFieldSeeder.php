<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\SystemFields;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\FieldRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;

final class SystemFieldSeeder
{
    public function __construct(
        private readonly FieldRepository $fieldRepository,
    ) {
    }

    public function seedFor(SchemaModel $schema): void
    {
        foreach (SystemFieldPolicy::schemaFieldDefinitions() as $definition) {
            $fullPath = FieldPath::fromString((string) $definition['full_path']);
            if ($this->fieldRepository->pathExists($schema, $fullPath)) {
                continue;
            }

            $parentId = null;
            $parentPath = $definition['parent_full_path'] ?? null;
            if (is_string($parentPath) && $parentPath !== '') {
                $parent = $this->fieldRepository->findByPath($schema, FieldPath::fromString($parentPath));
                $parentId = $parent?->id;
            }

            $this->fieldRepository->create([
                'schema_id' => (int) $schema->id,
                'parent_id' => $parentId,
                'name' => (string) $definition['name'],
                'full_path' => (string) $definition['full_path'],
                'type' => (string) $definition['type'],
                'cardinality' => (string) ($definition['cardinality'] ?? 'one'),
                'is_indexed' => (bool) ($definition['is_indexed'] ?? true),
                'is_system' => true,
                'validation_rules' => $definition['validation_rules'] ?? null,
                'sort_order' => (int) ($definition['sort_order'] ?? 0),
                'metadata' => $definition['metadata'] ?? null,
            ]);
        }
    }
}
