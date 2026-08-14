<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Polymorph\Platform\Domain\DataPlatform\Projection\FieldProjectionChanges;
use Polymorph\Platform\Domain\DataPlatform\Query\CompiledPredicate;
use Polymorph\Platform\Domain\DataPlatform\Query\QueryPredicate;

interface FieldTypeHandler
{
    public function type(): string;

    public function validateSchema(FieldDefinition $field): void;

    public function normalize(mixed $value, FieldDefinition $field, string $occurrence): mixed;

    public function validateValue(mixed $value, FieldDefinition $field, string $occurrence): void;

    public function collectBatchDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        DependencySet $dependencies,
    ): void;

    public function validateResolvedDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        ResolvedDependencies $dependencies,
    ): void;

    public function buildProjectionChanges(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
    ): FieldProjectionChanges;

    /** @return list<string> */
    public function supportedQueryOperators(): array;

    public function compileQuery(QueryPredicate $predicate): CompiledPredicate;
}
