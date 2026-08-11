<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\Users\Infrastructure\Repositories\UserRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Sdk\Access\AccessGrants;
use Polymorph\Sdk\Access\CapabilityAction;
use Polymorph\Sdk\Extension\ExtensionContext;

/**
 * Host-адаптер {@see AccessGrants}, СКОУПНУТЫЙ на расширение через
 * {@see ExtensionContext}.
 *
 * Улучшение безопасности над V1: V1-адаптер был глобальным и проверял лишь
 * `plugin.{любой-slug}.…` — то есть плагин мог выдавать гранты на ресурсы ЧУЖОГО
 * плагина. Здесь enforcement привязан к ПРЕФИКСУ ИМЕННО ЭТОГО расширения
 * ('ext.{id}.'), поэтому расширение не может управлять доступом к чужим ресурсам.
 */
final class SdkAccessGrants implements AccessGrants
{
    private const GRANT_PRIORITY = 100;

    public function __construct(
        private readonly AccessControlAdministration $admin,
        private readonly AccessGate $gate,
        private readonly UserRepository $users,
        private readonly ExtensionContext $context,
    ) {}

    public function grantToUser(int $userId, string $resource, string $action = CapabilityAction::ACCESS): void
    {
        $resource = $this->assertOwnResource($resource);

        $policy = $this->admin->ensurePolicy([
            'resource_pattern' => $resource,
            'action' => $action,
            'effect' => CapabilityCatalog::EFFECT_ALLOW,
            'priority' => self::GRANT_PRIORITY,
            'is_active' => true,
            'metadata' => ['source' => 'extension_grant', 'extension_id' => $this->context->id->value],
        ]);

        $this->admin->assign((int) $policy->id, Subject::user($userId));
    }

    public function revokeFromUser(int $userId, string $resource, string $action = CapabilityAction::ACCESS): void
    {
        $resource = $this->assertOwnResource($resource);
        $subject = (string) Subject::user($userId);

        $assignments = Assignment::query()
            ->where('subject', $subject)
            ->whereHas('policy', function ($query) use ($resource, $action): void {
                $query->where('resource_pattern', $resource)
                    ->where('action', $action)
                    ->where('effect', CapabilityCatalog::EFFECT_ALLOW);
            })
            ->get();

        foreach ($assignments as $assignment) {
            $this->admin->unassign((int) $assignment->policy_id, (int) $assignment->id);
        }
    }

    public function revokeResourceFromAll(array $resources, string $action = CapabilityAction::ACCESS): void
    {
        if ($resources === []) {
            return;
        }

        $resources = array_values(array_unique(array_map(
            fn (string $resource): string => $this->assertOwnResource($resource),
            $resources,
        )));

        $this->admin->revokeResource($resources, $action, CapabilityCatalog::EFFECT_ALLOW);
    }

    public function replaceUserGrants(int $userId, string $resourcePrefix, array $resources, string $action = CapabilityAction::ACCESS): void
    {
        $resourcePrefix = $this->assertOwnResource($resourcePrefix);
        $resources = array_values(array_unique(array_map(
            fn (string $resource): string => $this->assertOwnResource($resource),
            $resources,
        )));

        foreach ($resources as $resource) {
            if (! str_starts_with($resource, $resourcePrefix)) {
                throw new \InvalidArgumentException("Resource '{$resource}' is outside prefix '{$resourcePrefix}'.");
            }
        }

        DB::transaction(function () use ($userId, $resourcePrefix, $resources, $action): void {
            $current = $this->currentUserAssignments($userId, $resourcePrefix, $action);

            foreach (array_diff(array_keys($current), $resources) as $staleResource) {
                $assignment = $current[$staleResource];
                $this->admin->unassign((int) $assignment->policy_id, (int) $assignment->id);
            }

            foreach (array_diff($resources, array_keys($current)) as $newResource) {
                $this->grantToUser($userId, $newResource, $action);
            }
        });
    }

    public function userCan(int $userId, string $resource, string $action = CapabilityAction::ACCESS): bool
    {
        $resource = $this->assertOwnResource($resource);

        return $this->gate->allows($this->actorFor($userId), ResourceRef::fromString($resource), $action);
    }

    public function userCanBatch(int $userId, array $resources, string $action = CapabilityAction::ACCESS): array
    {
        if ($resources === []) {
            return [];
        }

        $resources = array_values(array_map(
            fn (string $resource): string => $this->assertOwnResource($resource),
            $resources,
        ));

        $checks = array_map(
            static fn (string $resource): AccessCheck => new AccessCheck(ResourceRef::fromString($resource), $action),
            $resources,
        );

        $allowed = $this->gate->allowsEach($this->actorFor($userId), $checks);

        $result = [];
        foreach ($resources as $index => $resource) {
            $result[$resource] = $allowed[$index] ?? false;
        }

        return $result;
    }

    public function userGrantsByPrefix(string $resourcePrefix, string $action = CapabilityAction::ACCESS): array
    {
        $resourcePrefix = $this->assertOwnResource($resourcePrefix);

        $rows = Assignment::query()
            ->where('subject', 'like', 'user:%')
            ->whereHas('policy', function ($query) use ($resourcePrefix, $action): void {
                $query->where('resource_pattern', 'like', $this->escapeLike($resourcePrefix).'%')
                    ->where('action', $action)
                    ->where('effect', CapabilityCatalog::EFFECT_ALLOW)
                    ->where('is_active', true);
            })
            ->with('policy:id,resource_pattern')
            ->get();

        $grants = [];
        foreach ($rows as $assignment) {
            $policy = $assignment->policy;
            if (! $policy instanceof Policy) {
                continue;
            }

            $userId = (int) substr((string) $assignment->subject, strlen('user:'));
            if ($userId <= 0) {
                continue;
            }

            $grants[$userId][] = (string) $policy->resource_pattern;
        }

        return $grants;
    }

    /**
     * @return array<string, Assignment> resource => assignment
     */
    private function currentUserAssignments(int $userId, string $resourcePrefix, string $action): array
    {
        $rows = Assignment::query()
            ->where('subject', (string) Subject::user($userId))
            ->whereHas('policy', function ($query) use ($resourcePrefix, $action): void {
                $query->where('resource_pattern', 'like', $this->escapeLike($resourcePrefix).'%')
                    ->where('action', $action)
                    ->where('effect', CapabilityCatalog::EFFECT_ALLOW);
            })
            ->with('policy:id,resource_pattern')
            ->get();

        $assignments = [];
        foreach ($rows as $assignment) {
            $policy = $assignment->policy;
            if ($policy instanceof Policy) {
                $assignments[(string) $policy->resource_pattern] = $assignment;
            }
        }

        return $assignments;
    }

    /**
     * null — «неизвестный или неактивный аккаунт», для гейта это deny.
     */
    private function actorFor(int $userId): ?User
    {
        $user = $this->users->find($userId);

        if ($user === null || ! $user->isActiveAccount()) {
            return null;
        }

        return $user;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Ресурс обязан принадлежать namespace ИМЕННО этого расширения: 'ext.{id}.…'.
     */
    private function assertOwnResource(string $resource): string
    {
        $resource = trim($resource);
        $prefix = $this->context->resourcePrefix();

        if (! str_starts_with($resource, $prefix) || strlen($resource) <= strlen($prefix)) {
            throw new \InvalidArgumentException(
                "Extension '{$this->context->id->value}' grants must target '{$prefix}…' resources, got '{$resource}'.",
            );
        }

        return $resource;
    }
}
