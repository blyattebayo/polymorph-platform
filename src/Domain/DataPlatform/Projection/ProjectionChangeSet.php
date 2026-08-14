<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

final class ProjectionChangeSet
{
    /** @var list<array<string, mixed>> */
    public array $refEdges = [];

    /** @var list<array<string, mixed>> */
    public array $mediaEdges = [];

    /** @var list<array<string, mixed>> */
    public array $uniqueValues = [];

    /** @var list<string> */
    public array $searchValues = [];

    public ?string $displayValue = null;

    public int $searchProjectionVersion = 1;

    public int $displayProjectionVersion = 1;

    public function observeField(FieldDefinition $field): void
    {
        if (($field->metadata['search'] ?? false) === true) {
            $this->searchProjectionVersion = max($this->searchProjectionVersion, $field->projectionVersion);
        }

        // A definition-level display template may read any schema field, so
        // its aggregate version must advance when any contributing shape does.
        $this->displayProjectionVersion = max($this->displayProjectionVersion, $field->projectionVersion);
    }

    public function merge(FieldProjectionChanges $changes): void
    {
        array_push($this->refEdges, ...$changes->refEdges);
        array_push($this->mediaEdges, ...$changes->mediaEdges);
        array_push($this->uniqueValues, ...$changes->uniqueValues);
        array_push($this->searchValues, ...$changes->searchValues);
        $this->displayValue ??= $changes->displayValue;
    }
}
