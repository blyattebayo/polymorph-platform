<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Authentication;

use Illuminate\Http\Request;

/** One route selects exactly one credential resolver. */
interface RequestCredentialResolver
{
    public function authenticate(Request $request): ?AuthenticatedCredential;
}
