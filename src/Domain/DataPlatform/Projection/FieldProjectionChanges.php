<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

final readonly class FieldProjectionChanges
{
    /**
     * @param  list<array<string, mixed>>  $refEdges
     * @param  list<array<string, mixed>>  $mediaEdges
     * @param  list<array<string, mixed>>  $uniqueValues
     * @param  list<string>  $searchValues
     */
    public function __construct(
        public array $refEdges = [],
        public array $mediaEdges = [],
        public array $uniqueValues = [],
        public array $searchValues = [],
        public ?string $displayValue = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
