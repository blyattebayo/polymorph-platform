<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Middleware\Concerns;

use Illuminate\Http\Request;

trait ExtractsJwtAccessToken
{
    protected function extractAccessToken(Request $request): string
    {
        $bearerToken = trim((string) $request->bearerToken());
        if ($bearerToken !== '') {
            return $bearerToken;
        }

        $cookieName = (string) config('jwt.cookies.access', 'cms_at');

        $cookieToken = trim((string) $request->cookie($cookieName, ''));
        if ($cookieToken !== '') {
            return $cookieToken;
        }

        $cookieHeader = (string) $request->header('Cookie', '');
        if ($cookieHeader !== '' && preg_match('/' . preg_quote($cookieName, '/') . '=([^;]+)/', $cookieHeader, $matches)) {
            return urldecode((string) $matches[1]);
        }

        return '';
    }
}
