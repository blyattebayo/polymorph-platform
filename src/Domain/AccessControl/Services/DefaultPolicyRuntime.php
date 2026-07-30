<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CompiledPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ResourceMatcher;
use Polymorph\Platform\Domain\AccessControl\Core\Models\CompiledPolicy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Decision;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

final class DefaultPolicyRuntime implements PolicyRuntime
{
    public function __construct(
        private readonly CompiledPolicyRepository $compiledPolicies,
        private readonly ResourceMatcher $resourceMatcher,
    ) {}

    public function allows(array $subjects, string $resource, string $action): bool
    {
        return $this->evaluate($subjects, $resource, $action)->allowed();
    }

    public function evaluate(array $subjects, string $resource, string $action): Decision
    {
        $subjectKeys = $this->normalizeSubjectKeys($subjects);

        if ($subjectKeys === [] || $resource === '' || $action === '') {
            return Decision::deny('no_matching_policy');
        }

        $candidates = $this->compiledPolicies->findForSubjects($subjectKeys, [$action, '*']);
        $matched = $this->matchPolicies($candidates, $resource, $action);

        return $this->evaluateMatchedPolicies($matched);
    }

    public function batchEvaluate(array $subjects, array $checks): array
    {
        if ($checks === []) {
            return [];
        }

        $subjectKeys = $this->normalizeSubjectKeys($subjects);

        if ($subjectKeys === []) {
            return array_map(static fn (): Decision => Decision::deny('no_matching_policy'), $checks);
        }

        $actions = [];
        foreach ($checks as $check) {
            $action = (string) ($check['action'] ?? '');
            if ($action === '') {
                continue;
            }

            $actions[$action] = true;
        }
        $actions['*'] = true;

        $candidates = $this->compiledPolicies->findForSubjects($subjectKeys, array_keys($actions));
        $decisions = [];

        foreach ($checks as $check) {
            $resource = (string) ($check['resource'] ?? '');
            $action = (string) ($check['action'] ?? '');

            if ($resource === '' || $action === '') {
                $decisions[] = Decision::deny('no_matching_policy');

                continue;
            }

            $matched = $this->matchPolicies($candidates, $resource, $action);
            $decisions[] = $this->evaluateMatchedPolicies($matched);
        }

        return $decisions;
    }

    /**
     * @param  iterable<CompiledPolicy>  $policies
     * @return list<CompiledPolicy>
     */
    private function matchPolicies(iterable $policies, string $resource, string $action): array
    {
        if ($resource === '' || $action === '') {
            return [];
        }

        $matched = [];

        foreach ($policies as $policy) {
            $policyAction = (string) $policy->action;
            if ($policyAction === '' || ($policyAction !== '*' && $policyAction !== $action)) {
                continue;
            }

            $policyResource = (string) $policy->resource_pattern;
            if (! $this->resourceMatcher->matches($policyResource, $resource)) {
                continue;
            }

            $matched[] = $policy;
        }

        return $matched;
    }

    /**
     * @param  list<Subject>  $subjects
     * @return list<string>
     */
    private function normalizeSubjectKeys(array $subjects): array
    {
        return array_values(array_unique(array_map(static fn (Subject $subject): string => (string) $subject, $subjects)));
    }

    /**
     * Семантика конфликтов — «deny overrides»: любой совпавший deny побеждает
     * независимо от priority.
     *
     * Раньше выигрывала ТОЛЬКО группа с минимальным priority («first-tier-wins»):
     * deny с priority 200 молча проигрывал allow со 100 — при том что 100 был
     * дефолтом всего сидинга, то есть почти любой рукотворный deny оказывался
     * мёртвым (аудит, B3). Ни одно штатное правило на «allow перекрывает deny
     * номером» не опиралось: точечные исключения делаются НЕназначением deny
     * субъекту, а не приоритетной гонкой. Поле priority осталось данными
     * (сортировка, отображение), в решении конфликтов оно не участвует.
     *
     * @param  iterable<CompiledPolicy>  $matchedPolicies
     */
    private function evaluateMatchedPolicies(iterable $matchedPolicies): Decision
    {
        $hasAny = false;
        $hasDeny = false;
        $denyPolicyIds = [];
        $allowPolicyIds = [];

        foreach ($matchedPolicies as $policy) {
            $effect = $this->effectOf($policy);
            if ($effect === null) {
                continue;
            }

            $hasAny = true;
            $policyId = $this->policyIdOf($policy);

            if ($effect->isDeny()) {
                $hasDeny = true;
                if ($policyId !== null) {
                    $denyPolicyIds[] = $policyId;
                }

                continue;
            }

            if ($policyId !== null) {
                $allowPolicyIds[] = $policyId;
            }
        }

        if (! $hasAny) {
            return Decision::deny('no_matching_policy');
        }

        if ($hasDeny) {
            return Decision::deny('explicit_deny', matchedPolicyIds: $this->uniqueIds($denyPolicyIds));
        }

        return Decision::allow('explicit_allow', matchedPolicyIds: $this->uniqueIds($allowPolicyIds));
    }

    private function effectOf(CompiledPolicy $policy): ?Effect
    {
        return $policy->effect instanceof Effect
            ? $policy->effect
            : null;
    }

    private function policyIdOf(CompiledPolicy $policy): ?int
    {
        $rawPolicyId = $policy->policy_id ?? $policy->id;
        if ($rawPolicyId === null) {
            return null;
        }

        $policyId = (int) $rawPolicyId;

        return $policyId > 0 ? $policyId : null;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique($ids));
    }
}
