<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Http\Middleware\Concerns\ExtractsJwtAccessToken;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Illuminate\Http\Request;

final class RequestCredentialAuthenticator
{
    use ExtractsJwtAccessToken;
    use ThrowsErrors;

    public const FAILURE_REASON_ATTRIBUTE = 'auth.failure_reason';

    public const FAILURE_MESSAGE_ATTRIBUTE = 'auth.failure_message';

    private const MESSAGES = [
        'missing_token' => 'Access token cookie is missing.',
        'invalid_token' => 'Access token is invalid.',
        'inactive_user' => 'User account is not active.',
    ];

    public function __construct(
        private readonly CompositeCredentialAuthenticator $credentials,
    ) {}

    public function authenticate(Request $request, bool $required = false): ?AuthenticatedCredential
    {
        $token = $this->extractAccessToken($request);
        if ($token === '') {
            $this->markFailure($request, 'missing_token');

            if ($required) {
                $this->respondUnauthorized('missing_token');
            }

            return null;
        }

        $credential = $this->credentials->authenticate($token);
        if ($credential === null) {
            $this->markFailure($request, 'invalid_token');

            if ($required) {
                $this->respondUnauthorized('invalid_token');
            }

            return null;
        }

        if (! $credential->user->isActiveAccount()) {
            $this->markFailure($request, 'inactive_user');

            if ($required) {
                $this->respondUnauthorized('inactive_user');
            }

            return null;
        }

        $request->attributes->set(UserIdentity::class, $credential->user);
        $request->attributes->set(AuthenticatedCredential::REQUEST_ATTRIBUTE, $credential);
        $request->attributes->set('auth_method', $credential->method());

        if ($credential->isPat() && $credential->personalAccessToken !== null) {
            $request->attributes->set('pat_token_id', (int) $credential->personalAccessToken->id);
        }

        $request->attributes->remove(self::FAILURE_REASON_ATTRIBUTE);
        $request->attributes->remove(self::FAILURE_MESSAGE_ATTRIBUTE);

        return $credential;
    }

    public static function failureMessage(string $reason): string
    {
        return self::MESSAGES[$reason] ?? 'Unknown authentication failure.';
    }

    private function markFailure(Request $request, string $reason): void
    {
        $request->attributes->set(self::FAILURE_REASON_ATTRIBUTE, $reason);
        $request->attributes->set(self::FAILURE_MESSAGE_ATTRIBUTE, self::failureMessage($reason));
    }

    private function respondUnauthorized(string $reason): never
    {
        $this->throwErrorWithHeaders(
            ErrorCode::UNAUTHORIZED,
            detail: null,
            meta: [
                'reason' => $reason,
                'message' => self::failureMessage($reason),
            ],
            headers: [
                'WWW-Authenticate' => 'Bearer',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
