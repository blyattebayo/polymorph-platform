<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Controllers;

use Polymorph\Platform\Domain\Auth\Application\DTO\LoginSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\LogoutSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\RefreshSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\DTO\RegisterSessionCommand;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\AuthSessionUnauthorizedException;
use Polymorph\Platform\Domain\Auth\Application\Support\AuthenticatedCredentialResolver;
use Polymorph\Platform\Domain\Auth\Application\Support\UserCapabilitiesPresenter;
use Polymorph\Platform\Domain\Auth\Application\UseCases\LoginSession;
use Polymorph\Platform\Domain\Auth\Application\UseCases\LogoutSession;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RefreshSession;
use Polymorph\Platform\Domain\Auth\Application\UseCases\RegisterSession;
use Polymorph\Platform\Domain\Auth\Http\Requests\LoginRequest;
use Polymorph\Platform\Domain\Auth\Http\Requests\LogoutRequest;
use Polymorph\Platform\Domain\Auth\Http\Requests\RefreshRequest;
use Polymorph\Platform\Domain\Auth\Http\Requests\RegisterRequest;
use Polymorph\Platform\Domain\Auth\Http\Resources\LoginResource;
use Polymorph\Platform\Domain\Auth\Http\Resources\LogoutResource;
use Polymorph\Platform\Domain\Auth\Http\Resources\TokenRefreshResource;
use Polymorph\Platform\Domain\Auth\Infrastructure\Http\AuthCookieFactory;
use Polymorph\Platform\Domain\Users\Http\Resources\UserResource;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorKernel;
use Polymorph\Platform\Support\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для управления аутентификацией.
 *
 * Объединяет все операции аутентификации в одном контроллере:
 * - Login (вход в систему)
 * - Logout (выход из системы)
 * - Refresh (обновление токенов)
 * - Current (получение текущего пользователя)
 */
final class AuthController
{
    public function __construct(
        private readonly LoginSession $loginSession,
        private readonly LogoutSession $logoutSession,
        private readonly RefreshSession $refreshSession,
        private readonly RegisterSession $registerSession,
        private readonly CurrentActorResolver $currentActor,
        private readonly AuthenticatedCredentialResolver $credentialResolver,
        private readonly UserCapabilitiesPresenter $capabilities,
        private readonly AuthCookieFactory $cookies,
        private readonly ErrorKernel $errors,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        if (! (bool) config('auth.self_registration.enabled', false)) {
            // Фича выключена — не раскрываем существование эндпоинта.
            return ErrorResponseFactory::make(
                $this->errors->factory()->for(ErrorCode::NOT_FOUND)->build(),
            );
        }

        $result = $this->registerSession->execute(new RegisterSessionCommand(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            name: $request->filled('name') ? (string) $request->input('name') : null,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        $response = (new LoginResource(
            $result->user,
            $result->capabilities,
        ))->toResponse($request)->setStatusCode(201);

        return $this->withCookies($response, $this->cookies->pair($result->accessToken, $result->refreshToken));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginSession->execute(new LoginSessionCommand(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        $response = (new LoginResource(
            $result->user,
            $result->capabilities,
        ))->toResponse($request);

        return $this->withCookies($response, $this->cookies->pair($result->accessToken, $result->refreshToken));
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $logoutAll = (bool) $request->boolean('all', false);
        $actor = $this->currentActor->requireUser();
        $credential = $this->credentialResolver->fromRequest($request);

        $this->logoutSession->execute(new LogoutSessionCommand(
            actor: $actor,
            allDevices: $logoutAll,
            refreshToken: (string) $request->cookie($this->cookies->refreshName(), ''),
            sessionId: $credential?->sessionId,
        ));

        $response = (new LogoutResource)->toResponse($request);

        return $this->withCookies($response, $this->cookies->forgetPair());
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        try {
            $result = $this->refreshSession->execute(new RefreshSessionCommand(
                refreshToken: (string) $request->cookie($this->cookies->refreshName(), ''),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (AuthSessionUnauthorizedException $exception) {
            return $this->withCookies(
                ErrorResponseFactory::make($this->errors->resolve($exception)),
                $this->cookies->forgetPair(),
            );
        }

        $response = (new TokenRefreshResource)->toResponse($request);

        return $this->withCookies($response, $this->cookies->pair($result->accessToken, $result->refreshToken));
    }

    public function current(): UserResource
    {
        $user = $this->currentActor->requireUser();

        return new UserResource(
            $user,
            $this->capabilities->for($user),
        );
    }

    /**
     * @param  list<\Symfony\Component\HttpFoundation\Cookie>  $cookies
     */
    private function withCookies(JsonResponse $response, array $cookies): JsonResponse
    {
        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
