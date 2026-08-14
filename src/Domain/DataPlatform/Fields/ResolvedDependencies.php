<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

final readonly class ResolvedDependencies
{
    /**
     * @param  array<int, array{id:int,record_definition_id:int,deleted_at:mixed}>  $records
     * @param  array<string, array<string, mixed>>  $media
     */
    public function __construct(
        public array $records,
        public array $media,
    ) {}
}
