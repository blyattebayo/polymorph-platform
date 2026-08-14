<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Illuminate\Contracts\Container\Container;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Fields\DocumentPathAccessor;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Sdk\Data\Migrations\DocumentTransformer;

final class MigrationOperationExecutor
{
    public const OPERATIONS = [
        'rename_field', 'move_field', 'add_field', 'remove_field', 'change_type',
        'change_cardinality', 'update_constraints', 'split', 'merge', 'rebuild_projections',
    ];

    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly DocumentPathAccessor $paths,
        private readonly Container $container,
    ) {}

    /** @param list<MigrationOperation> $operations @param array<string,FieldDefinition> $targetFields @return array<string,mixed> */
    public function execute(array $document, array $operations, array $targetFields): array
    {
        foreach ($operations as $operation) {
            $kind = $operation->kind;
            $document = match ($kind) {
                'rename_field', 'move_field' => $this->move($document, (string) $operation->argument('from'), (string) $operation->argument('to')),
                'add_field' => $this->add($document, (string) $operation->argument('path'), $operation->argument('default')),
                'remove_field' => $this->paths->remove($document, (string) $operation->argument('path')),
                'change_type' => $this->changeType($document, $operation, $targetFields),
                'change_cardinality' => $this->changeCardinality($document, $operation),
                'split', 'merge' => $this->custom($document, $operation),
                'update_constraints', 'rebuild_projections' => $document,
            };
        }

        return $document;
    }

    /** @return array<string,mixed> */
    private function changeType(array $document, MigrationOperation $operation, array $targetFields): array
    {
        $path = (string) $operation->argument('path');
        $field = $targetFields[$path] ?? null;
        if (! $field instanceof FieldDefinition) {
            throw DataPlatformBadRequest::because(
                'migration_target_field_missing',
                "Target field '{$path}' does not exist.",
                ['path' => $path],
            );
        }
        $handler = $this->types->get($field->type);

        return $this->paths->map($document, $path, static function (mixed $value, string $occurrence) use ($handler, $field): mixed {
            $normalized = $handler->normalize($value, $field, $occurrence);
            $handler->validateValue($normalized, $field, $occurrence);

            return $normalized;
        });
    }

    /** @return array<string,mixed> */
    private function changeCardinality(array $document, MigrationOperation $operation): array
    {
        $path = (string) $operation->argument('path');
        $to = (string) $operation->argument('to');

        return $this->paths->map($document, $path, static function (mixed $value) use ($to, $path): mixed {
            if ($to === 'many') {
                return $value === null ? [] : (is_array($value) && array_is_list($value) ? $value : [$value]);
            }
            if ($to === 'one') {
                if (! is_array($value) || ! array_is_list($value)) {
                    return $value;
                }
                if (count($value) > 1) {
                    throw DataPlatformBadRequest::because(
                        'cardinality_collapse_requires_transformer',
                        "Cannot collapse '{$path}' to cardinality one without a transformer.",
                        ['path' => $path],
                    );
                }

                return $value[0] ?? null;
            }

            throw DataPlatformBadRequest::because(
                'invalid_target_cardinality',
                "Invalid target cardinality '{$to}'.",
                ['cardinality' => $to],
            );
        });
    }

    /** @return array<string,mixed> */
    private function custom(array $document, MigrationOperation $operation): array
    {
        $class = (string) $operation->argument('transformer');
        if ($class === '' || ! is_a($class, DocumentTransformer::class, true)) {
            throw DataPlatformBadRequest::because(
                'missing_document_transformer',
                'Split/merge requires an SDK DocumentTransformer class.',
                ['transformer' => $class],
            );
        }
        $transformer = $this->container->make($class);
        if (! $transformer instanceof DocumentTransformer) {
            throw DataPlatformInvariantViolation::because(
                'document_transformer_resolution_failed',
                "Transformer '{$class}' could not be resolved.",
                ['transformer' => $class],
            );
        }

        return $transformer->transform($document, $operation->toArray());
    }

    /** @return array<string,mixed> */
    private function move(array $document, string $from, string $to): array
    {
        [$exists, $value] = $this->paths->get($document, $from);
        if (! $exists) {
            return $document;
        }

        return $this->paths->set($this->paths->remove($document, $from), $to, $value);
    }

    /** @return array<string,mixed> */
    private function add(array $document, string $path, mixed $default): array
    {
        [$exists] = $this->paths->get($document, $path);

        return $exists ? $document : $this->paths->set($document, $path, $default);
    }
}
