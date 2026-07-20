<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

interface PolicyCompilationService
{
    public function recompileSubject(Subject $subject): void;

    public function recompileAffectedSubjects(int $policyId): void;

    public function recompileAll(): int;
}
