<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

interface AccessControlAdministration
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPolicy(array $data): Policy;

    /**
     * @param  array<string, mixed>  $data
     */
    public function ensurePolicy(array $data): Policy;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePolicy(int $id, array $data): Policy;

    public function deletePolicy(int $id): void;

    /**
     * Delete exact policies for resources together with their assignments.
     * This is the idempotent lifecycle operation used when protected objects are deleted.
     *
     * @param  list<string>  $resourcePatterns
     */
    public function revokeResource(
        array $resourcePatterns,
        string $action = CapabilityCatalog::ACTION_ACCESS,
    ): void;

    public function assign(int $policyId, Subject $subject): Assignment;

    public function unassign(int $assignmentId): void;

    /**
     * @param  list<int>  $policyIds
     */
    public function setSubjectPolicies(Subject $subject, array $policyIds): void;
}
