<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\OAuth;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthTokenSet;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthProtocolException;

final class OAuthResponses
{
    public static function error(OAuthProtocolException $error): JsonResponse
    {
        return response()->json([
            'error' => $error->error,
            'error_description' => $error->getMessage(),
        ], $error->status)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    public static function tokens(OAuthTokenSet $tokens): JsonResponse
    {
        return response()->json([
            'access_token' => $tokens->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokens->expiresIn,
            'refresh_token' => $tokens->refreshToken,
            'scope' => $tokens->scope,
        ])->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
