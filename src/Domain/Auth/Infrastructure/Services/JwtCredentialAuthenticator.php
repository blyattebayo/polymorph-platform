<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services;

use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtVerificationException;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\AuthenticatedCredential;
use Polymorph\Platform\Domain\Auth\Core\ValueObjects\CredentialKind;
use Polymorph\Platform\Domain\Users\Queries\FindUserByIdQuery;

final class JwtCredentialAuthenticator
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly FindUserByIdQuery $findUserById,
        private readonly AccessTokenDenylist $denylist,
    ) {}

    public function authenticate(string $token): ?AuthenticatedCredential
    {
        try {
            $verified = $this->jwt->verify($token, 'access');
            $claims = is_array($verified['claims'] ?? null) ? $verified['claims'] : [];
            $subject = $claims['sub'] ?? null;

            if (! is_scalar($subject) || trim((string) $subject) === '') {
                return null;
            }

            $sessionIdClaim = $claims['sid'] ?? null;
            $sessionId = is_numeric($sessionIdClaim) ? (int) $sessionIdClaim : null;

            if ($sessionId !== null && $this->denylist->isRevoked($sessionId)) {
                return null;
            }

            $user = $this->findUserById->execute((int) $subject);
            if ($user === null) {
                return null;
            }

            return new AuthenticatedCredential(
                $user,
                CredentialKind::JwtSession,
                sessionId: $sessionId,
            );
        } catch (JwtConfigurationException $exception) {
            throw $exception;
        } catch (JwtVerificationException|\UnexpectedValueException|\InvalidArgumentException|\DomainException) {
            // Невалидный/просроченный/повреждённый токен: ExpiredException,
            // SignatureInvalidException, BeforeValidException (все наследуют
            // \UnexpectedValueException) + ошибки декодирования Firebase JWT.
            // Инфраструктурные и программные ошибки (PDOException, TypeError, …)
            // намеренно НЕ глотаем — они должны всплывать как 500, не как 401.
            return null;
        }
    }
}
