<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\Models\CompiledPolicy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CompiledPolicyData;
use Illuminate\Support\Collection;

interface CompiledPolicyRepository
{
    /**
     * @param list<CompiledPolicyData> $rows
     */
    public function replaceForSubject(string $subject, array $rows): void;

    /**
     * @param list<string> $subjects
     * @param list<string> $actions
     * @return Collection<int, CompiledPolicy>
     */
    public function findForSubjects(array $subjects, array $actions = []): Collection;

    /**
     * @return Collection<int, CompiledPolicy>
     */
    public function listBySubject(string $subject): Collection;

    /**
     * @return Collection<int, string>
     */
    public function subjects(): Collection;

    public function clear(): void;
}
