<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Decision;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

interface PolicyRuntime
{
    /**
    * @param list<Subject> $subjects
     */
    public function allows(array $subjects, string $resource, string $action): bool;

    /**
    * @param list<Subject> $subjects
     */
    public function evaluate(array $subjects, string $resource, string $action): Decision;

    /**
    * @param list<Subject> $subjects
     * @param list<array{resource:string,action:string}> $checks
     * @return list<Decision>
     */
    public function batchEvaluate(array $subjects, array $checks): array;
}