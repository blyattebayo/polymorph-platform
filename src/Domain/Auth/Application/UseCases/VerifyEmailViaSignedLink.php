<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\UseCases;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Polymorph\Platform\Domain\Auth\Application\DTO\EmailVerificationOutcome;
use Polymorph\Platform\Domain\Auth\Application\DTO\VerifyEmailViaSignedLinkCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\VerifyEmailViaSignedLinkResult;
use Polymorph\Platform\Domain\Users\Actions\MarkEmailVerifiedAction;
use Polymorph\Platform\Domain\Users\Core\Contracts\UserRepository;

final readonly class VerifyEmailViaSignedLink
{
    public function __construct(
        private UserRepository $users,
        private MarkEmailVerifiedAction $markEmailVerified,
    ) {}

    public function execute(VerifyEmailViaSignedLinkCommand $command): VerifyEmailViaSignedLinkResult
    {
        if (! $command->signatureValid) {
            return new VerifyEmailViaSignedLinkResult(EmailVerificationOutcome::Error);
        }

        $user = $this->users->find($command->userId);
        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $command->emailHash)) {
            return new VerifyEmailViaSignedLinkResult(EmailVerificationOutcome::Error);
        }

        if ($user->hasVerifiedEmail()) {
            return new VerifyEmailViaSignedLinkResult(EmailVerificationOutcome::AlreadyVerified);
        }

        $verifiedUser = $this->markEmailVerified->execute($user);
        Event::dispatch(new Verified($verifiedUser));

        return new VerifyEmailViaSignedLinkResult(EmailVerificationOutcome::Verified);
    }
}
