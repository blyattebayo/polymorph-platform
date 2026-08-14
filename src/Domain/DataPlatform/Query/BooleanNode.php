<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;

final readonly class BooleanNode implements FilterNode
{
    /** @param list<FilterNode> $children */
    public function __construct(
        public string $operator,
        public array $children,
    ) {
        if (! in_array($operator, ['and', 'or', 'not'], true)) {
            throw DataPlatformBadRequest::because(
                'invalid_boolean_operator',
                "Invalid boolean operator '{$operator}'.",
                ['operator' => $operator],
            );
        }
        if ($operator === 'not' && count($children) !== 1) {
            throw DataPlatformBadRequest::because('invalid_not_arity', 'A not node requires exactly one child.');
        }
    }
}
