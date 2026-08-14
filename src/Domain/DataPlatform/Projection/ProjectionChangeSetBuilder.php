<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldTypeRegistry;
use Polymorph\Platform\Domain\DataPlatform\Schema\CompiledSchemaTree;

/** Builds every synchronous projection from the canonical document and schema. */
final class ProjectionChangeSetBuilder
{
    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly DisplayTemplateRenderer $displayTemplates,
    ) {}

    public function beginOperation(): void
    {
        $this->displayTemplates->beginOperation();
    }

    /**
     * @param  array<string,mixed>  $document
     */
    public function build(int $definitionId, string $schemaVersionId, array $document, CompiledSchemaTree $tree): ProjectionChangeSet
    {
        $result = new ProjectionChangeSet;
        foreach ($tree->fields() as $field) {
            $result->observeField($field);
            $handler = $this->types->get($field->type);
            foreach ($tree->values($document, $field) as $value) {
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
