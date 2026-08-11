<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\Exceptions;

use LogicException;

/**
 * Signals a broken Auth domain invariant, not invalid HTTP input.
 */
final class AuthInvariantViolation extends LogicException {}
