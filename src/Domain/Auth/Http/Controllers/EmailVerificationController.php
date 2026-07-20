<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Polymorph\Platform\Domain\Auth\Application\DTO\ResendEmailVerificationNotificationCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\VerifyEmailViaSignedLinkCommand;
use Polymorph\Platform\Domain\Auth\Application\UseCases\ResendEmailVerificationNotification;
use Polymorph\Platform\Domain\Auth\Application\UseCases\VerifyEmailViaSignedLink;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\EmailVerificationRedirectFactory;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Подтверждение email по подписанной ссылке из письма + повторная отправка.
 *
 * verify() открывается человеком в браузере (ссылка из письма), поэтому отвечает
 * редиректом в SPA, а не JSON. auth:api здесь не нужен.
 */
final readonly class EmailVerificationController
{
    public function __construct(
        private CurrentActorResolver $currentActor,
        private VerifyEmailViaSignedLink $verifyEmail,
        private ResendEmailVerificationNotification $resendVerification,
        private EmailVerificationRedirectFactory $redirects,
    ) {}

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $result = $this->verifyEmail->execute(new VerifyEmailViaSignedLinkCommand(
            userId: $id,
            emailHash: $hash,
            signatureValid: $request->hasValidSignature(),
        ));

        return redirect()->to($this->redirects->withOutcome($result->outcome));
    }

    public function resend(): JsonResponse
    {
        $this->resendVerification->execute(new ResendEmailVerificationNotificationCommand(
            userId: $this->currentActor->requireUser()->userId(),
        ));

        // Идемпотентно и без раскрытия статуса: всегда 202.
        return response()->json(['status' => 'sent'], 202);
    }
}
