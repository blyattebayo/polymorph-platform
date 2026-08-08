<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ResourceMatcher;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\CredentialScopes;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\AuthenticationContext;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

/**
 * Единственное место, где «тройка» (резолвер актора, субъекты, рантайм политик)
 * склеивается в решение. Биндинг scoped: субъекты кэшируются на запрос.
 *
 * Решение — пересечение двух ограничений: что позволено СУБЪЕКТУ политиками и
 * что позволено CREDENTIAL, которым субъект пришёл ({@see CredentialScopes}).
 * Credential может только сужать: расширить права субъекта им нельзя.
 */
final class PolicyRuntimeAccessGate implements AccessGate
{
    public function __construct(
        private readonly PolicyRuntime $runtime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly AuthenticationContext $auth,
        private readonly ResourceMatcher $resourceMatcher,
    ) {}

    public function allows(?UserIdentity $actor, ResourceRef $resource, string $action): bool
    {
        if (! $actor instanceof UserIdentity) {
            return false;
        }

        $action = trim($action);

        if (! $this->credentialAllows($actor, $resource, $action)) {
            return false;
        }

        return $this->runtime->allows($this->subjectProvider->for($actor), $resource->value, $action);
    }

    public function currentActorAllows(ResourceRef $resource, string $action): bool
    {
        return $this->allows($this->auth->actor(), $resource, $action);
    }

    public function allowsEach(?UserIdentity $actor, array $checks): array
    {
        if ($checks === []) {
            return [];
        }

        if (! $actor instanceof UserIdentity) {
            return array_map(static fn (): bool => false, $checks);
        }

        $decisions = $this->runtime->batchEvaluate(
            $this->subjectProvider->for($actor),
            array_map(
                static fn (AccessCheck $check): array => [
                    'resource' => $check->resource->value,
                    'action' => trim($check->action),
                ],
                $checks,
            ),
        );

        // Ограничение credential накладывается поверх батча, а не сужает выборку
        // до него: батч — один SQL, и дробить его ради обычного случая
        // (ограничений нет) смысла нет.
        return array_map(
            fn ($decision, AccessCheck $check): bool => $decision->allowed()
                && $this->credentialAllows($actor, $check->resource, trim($check->action)),
            $decisions,
            $checks,
        );
    }

    /**
     * Ограничение credential применяется ТОЛЬКО когда спрашивают про актора
     * текущего запроса.
     *
     * Иначе ломается информационный вопрос «что может пользователь X»:
     * SdkAccessGrants::userCan() и EffectiveCapabilityResolver зовут гейт для
     * произвольного субъекта, и сужение чужого ответа областью своего токена
     * дало бы неверный ответ, а не более безопасный.
     */
    private function credentialAllows(UserIdentity $actor, ResourceRef $resource, string $action): bool
    {
        $scopes = $this->scopesOfCurrentRequest($actor);

        if ($scopes === null || $scopes->isUnrestricted()) {
            return true;
        }

        foreach ($scopes->entries() as $entry) {
            if ($this->actionCovers($entry->action, $action)
                && $this->resourceMatcher->matches($entry->resource->value, $resource->value)) {
                return true;
            }
        }

        return false;
    }

    private function scopesOfCurrentRequest(UserIdentity $actor): ?CredentialScopes
    {
        $credential = $this->auth->credential();

        if ($credential === null) {
            return null;
        }

        return $credential->user->userId() === $actor->userId() ? $credential->scopes : null;
    }

    /** Та же семантика, что у action политики: `*` покрывает всё, иначе точное совпадение. */
    private function actionCovers(string $granted, string $requested): bool
    {
        $granted = trim($granted);

        return $granted === CapabilityCatalog::ACTION_WILDCARD || $granted === $requested;
    }
}
