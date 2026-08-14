<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Fields\DocumentPathAccessor;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;

/** Builds every synchronous projection from the canonical document and schema. */
final class ProjectionChangeSetBuilder
{
    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly DocumentPathAccessor $paths,
        private readonly DisplayTemplateRenderer $displayTemplates,
    ) {}

    public function beginOperation(): void
    {
        $this->displayTemplates->beginOperation();
    }

    /**
     * @param  array<string,mixed>  $document
     * @param  list<FieldDefinition>  $fields
     */
    public function build(int $definitionId, string $schemaVersionId, array $document, array $fields): ProjectionChangeSet
    {
        $result = new ProjectionChangeSet;
        foreach ($fields as $field) {
            $result->observeField($field);
            $handler = $this->types->get($field->type);
            foreach ($this->paths->values($document, $field->path) as $value) {
                $result->merge($handler->buildProjectionChanges($value['value'], $field, $value['occurrence']));
            }
        }

        $result->refEdges = array_map(ProjectionEdgeIdentity::withItemId(...), $result->refEdges);
        $result->mediaEdges = array_map(ProjectionEdgeIdentity::withItemId(...), $result->mediaEdges);
        $templateDisplayValue = $this->displayTemplates->render($definitionId, $schemaVersionId, $document);
        if ($templateDisplayValue !== null) {
            $result->displayValue = $templateDisplayValue;
        }

        return $result;
    }
}
