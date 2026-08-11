<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Session;

use Polymorph\Platform\Domain\Auth\Application\Contracts\AuthenticationAudit;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared\BestEffortAudit;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

final readonly class LoggedAuthenticationAudit implements AuthenticationAudit
{
    public function __construct(
        private BestEffortAudit $audit,
        private AppLogger $logger,
    ) {}

    public function loggedIn(UserId $userId, ClientMetadata $client): void
    {
        $this->audit->record('auth_login_audit', fn () => $this->logger->event('auth.user_logged_in', [
            'user_id' => $userId->value,
            'ip' => $client->ip,
            'user_agent' => $client->userAgent,
        ]), ['user_id' => $userId->value]);
    }

    public function loggedOut(UserId $userId, bool $allDevices): void
    {
        $this->audit->record('auth_logout_audit', fn () => $this->logger->event('auth.user_logged_out', [
            'user_id' => $userId->value,
            'all_devices' => $allDevices,
        ]), ['user_id' => $userId->value]);
    }
}
