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
     * @param array<string, mixed> $data
     */
    public function createPolicy(array $data): Policy;

    /**
     * @param array<string, mixed> $data
     */
    public function ensurePolicy(array $data): Policy;

    /**
     * @param array<string, mixed> $data
     */
    public function updatePolicy(int $id, array $data): Policy;

    public function deletePolicy(int $id): void;

    /**
     * Отозвать доступ к набору ресурсов целиком: удаляет политики с точным
     * resource_pattern из набора вместе со всеми их назначениями (любые субъекты),
     * с пересчётом доступа и аудитом, одной транзакцией. Идемпотентно.
     * Применять при удалении самого защищаемого объекта.
     *
     * @param list<string> $resourcePatterns
     */
    public function revokeResource(
        array $resourcePatterns,
        string $action = CapabilityCatalog::ACTION_ACCESS,
        string $effect = CapabilityCatalog::EFFECT_ALLOW,
    ): void;

    public function assign(int $policyId, Subject $subject): Assignment;

    public function unassign(int $policyId, int $assignmentId): void;

    /**
     * @param list<int> $policyIds
     */
    public function setSubjectPolicies(Subject $subject, array $policyIds): void;
}
