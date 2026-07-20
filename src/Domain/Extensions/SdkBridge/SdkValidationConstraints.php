<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Support\Validation\ValidationConstraints as CoreValidationConstraints;
use Polymorph\Sdk\Validation\EmailConstraint;
use Polymorph\Sdk\Validation\PasswordConstraint;
use Polymorph\Sdk\Validation\PatternConstraint;
use Polymorph\Sdk\Validation\ValidationConstraints;

/**
 * Host-адаптер V2-контракта правил валидации к ядровому источнику
 * (config через Polymorph\Platform\Support\Validation\ValidationConstraints — единый источник правды).
 * Маппит ядровые VO в нейтральные V2 SDK VO (Polymorph\Sdk\Validation\*).
 */
final class SdkValidationConstraints implements ValidationConstraints
{
    public function password(): PasswordConstraint
    {
        $c = CoreValidationConstraints::password();

        return new PasswordConstraint($c->min(), $c->max());
    }

    public function email(): EmailConstraint
    {
        return new EmailConstraint(CoreValidationConstraints::email()->max());
    }

    public function slug(): PatternConstraint
    {
        return $this->pattern(CoreValidationConstraints::slug());
    }

    public function aclAction(): PatternConstraint
    {
        return $this->pattern(CoreValidationConstraints::aclAction());
    }

    public function roleCode(): PatternConstraint
    {
        return $this->pattern(CoreValidationConstraints::roleCode());
    }

    private function pattern(\Polymorph\Platform\Support\Validation\Constraints\PatternConstraint $core): PatternConstraint
    {
        return new PatternConstraint($core->pattern(), $core->max() ?? PHP_INT_MAX);
    }
}
