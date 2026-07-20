<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Polymorph\Platform\Domain\Auth\Http\Requests\ForgotPasswordRequest;
use Polymorph\Platform\Domain\Auth\Http\Requests\ResetPasswordRequest;
use Polymorph\Platform\Domain\Users\Actions\ChangePasswordAction;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final readonly class PasswordResetController
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink([
            'email' => strtolower((string) $request->validated('email')),
        ]);

        return AdminResponse::json([
            'message' => 'If the email exists, a password reset link will be sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request, ChangePasswordAction $changePassword): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::broker()->reset(
            [
                'email' => strtolower((string) $validated['email']),
                'token' => (string) $validated['token'],
                'password' => (string) $validated['password'],
                // password_confirmation нет в validated() (у него нет правила —
                // только модификатор confirmed), берём из входа напрямую.
                'password_confirmation' => (string) $request->input('password_confirmation'),
            ],
            static function ($user, string $password) use ($changePassword): void {
                if ($user instanceof User) {
                    $changePassword->execute($user, $password);
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return AdminResponse::json([
                'message' => 'Password reset token is invalid or expired.',
            ], 422);
        }

        return AdminResponse::json([
            'message' => 'Password has been reset.',
        ]);
    }
}
