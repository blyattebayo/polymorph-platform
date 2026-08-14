<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

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

    public function merge(FieldProjectionChanges $changes): void
    {
        array_push($this->refEdges, ...$changes->refEdges);
        array_push($this->mediaEdges, ...$changes->mediaEdges);
        array_push($this->uniqueValues, ...$changes->uniqueValues);
        array_push($this->searchValues, ...$changes->searchValues);
        $this->displayValue ??= $changes->displayValue;
    }
}
