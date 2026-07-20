<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Identity;

use Polymorph\Platform\Domain\Users\Core\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

final class CurrentActorResolver
{
    public function actor(): ?UserIdentity
    {
        $actor = $this->candidate();

        return $actor instanceof UserIdentity ? $actor : null;
    }

    public function requireActor(): UserIdentity
    {
        $actor = $this->actor();

        abort_unless($actor instanceof UserIdentity, 401);

        return $actor;
    }

    public function user(): ?User
    {
        $actor = $this->candidate();

        return $actor instanceof User ? $actor : null;
    }

    public function requireUser(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function authIdentifier(): int|string|null
    {
        $actor = $this->candidate();

        if (! $actor instanceof Authenticatable) {
            return null;
        }

        $identifier = $actor->getAuthIdentifier();

        if ($identifier === null) {
            return null;
        }

        if (is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }

        return (string) $identifier;
    }

    private function candidate(): mixed
    {
        $request = $this->request();

        if ($request instanceof Request) {
            return $request->attributes->get(UserIdentity::class, $request->user());
        }

        return Auth::user();
    }

    private function request(): ?Request
    {
        if (! App::bound('request')) {
            return null;
        }

        $request = App::make('request');

        return $request instanceof Request ? $request : null;
    }
}