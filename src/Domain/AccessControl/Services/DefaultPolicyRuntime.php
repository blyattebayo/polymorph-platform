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
     * @param  iterable<CompiledPolicy>  $matchedPolicies
     */
    private function evaluateMatchedPolicies(iterable $matchedPolicies): Decision
    {
        $prioritized = [];

        foreach ($matchedPolicies as $policy) {
            $effect = $this->effectOf($policy);
            if ($effect === null) {
                continue;
            }

            $priority = (int) $policy->priority;
            $prioritized[$priority][] = [
                'effect' => $effect,
                'policy_id' => $this->policyIdOf($policy),
            ];
        }

        if ($prioritized === []) {
            return Decision::deny('no_matching_policy');
        }

        ksort($prioritized, SORT_NUMERIC);
        $winningRules = reset($prioritized);
        if (! is_array($winningRules) || $winningRules === []) {
            return Decision::deny('no_matching_policy');
        }

        $denyPolicyIds = [];
        $allowPolicyIds = [];

        foreach ($winningRules as $rule) {
            $policyId = $rule['policy_id'];
            if ($rule['effect']->isDeny()) {
                if ($policyId !== null) {
                    $denyPolicyIds[] = $policyId;
                }

                continue;
            }

            if ($policyId !== null) {
                $allowPolicyIds[] = $policyId;
            }
        }

        if ($denyPolicyIds !== []) {
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
