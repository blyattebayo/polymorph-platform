<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Support;

use Illuminate\Http\Request;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;

final class AuthenticatedCredentialResolver
{
    public function __construct(
        private readonly CurrentActorResolver $currentActor,
    ) {}

    public function fromRequest(Request $request): ?AuthenticatedCredential
    {
        $credential = $request->attributes->get(AuthenticatedCredential::REQUEST_ATTRIBUTE);

        if ($credential instanceof AuthenticatedCredential) {
            return $credential;
        }

        $user = $this->currentActor->user();

        if ($user instanceof User) {
            return new AuthenticatedCredential($user, CredentialKind::JwtSession);
        }

        return null;
    }
}
