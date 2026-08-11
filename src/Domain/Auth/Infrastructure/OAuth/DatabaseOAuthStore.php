<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\OAuth;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\AuthorizationCode;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthClient;
use Polymorph\Platform\Domain\Auth\Application\OAuth\Data\OAuthGrant;
use Polymorph\Platform\Domain\Auth\Application\OAuth\OAuthStore;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;
use stdClass;

final class DatabaseOAuthStore implements OAuthStore
{
    public function registerClient(string $id, string $name, array $redirectUris, DateTimeImmutable $createdAt): void
    {
        DB::table('auth_oauth_clients')->insert([
            'client_id' => $id,
            'name' => $name,
            'redirect_uris' => json_encode($redirectUris, JSON_THROW_ON_ERROR),
            'created_at' => $this->timestamp($createdAt),
        ]);
    }

    public function client(string $id): ?OAuthClient
    {
        $row = DB::table('auth_oauth_clients')->where('client_id', $id)->first();
        if (! $row instanceof stdClass) {
            return null;
        }

        $redirectUris = json_decode((string) $row->redirect_uris, true, 512, JSON_THROW_ON_ERROR);

        return new OAuthClient((string) $row->client_id, (string) $row->name, array_values(array_map('strval', $redirectUris)));
    }

    public function saveAuthorizationCode(
        TokenHash $hash,
        AuthorizationCode $code,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): void {
        DB::table('auth_oauth_authorization_codes')->insert([
            'credential_hash' => strtolower($hash->value),
            'client_id' => $code->clientId,
            'user_id' => $code->userId,
            'redirect_uri' => $code->redirectUri,
            'resource' => $code->resource,
            'scope' => $code->scope,
            'code_challenge' => $code->codeChallenge,
            'expires_at' => $this->timestamp($expiresAt),
            'created_at' => $this->timestamp($createdAt),
        ]);
    }

    public function consumeAuthorizationCode(TokenHash $hash, DateTimeImmutable $now): ?AuthorizationCode
    {
        return DB::transaction(function () use ($hash, $now): ?AuthorizationCode {
            $row = DB::table('auth_oauth_authorization_codes')
                ->where('credential_hash', strtolower($hash->value))
                ->where('expires_at', '>', $this->timestamp($now))
                ->lockForUpdate()
                ->first();
            if (! $row instanceof stdClass) {
                return null;
            }

            DB::table('auth_oauth_authorization_codes')->where('credential_hash', strtolower($hash->value))->delete();

            return new AuthorizationCode(
                (string) $row->client_id,
                (int) $row->user_id,
                (string) $row->redirect_uri,
                (string) $row->resource,
                (string) $row->scope,
                (string) $row->code_challenge,
            );
        }, 3);
    }

    public function createGrant(
        OAuthGrant $grant,
        TokenHash $refreshHash,
        DateTimeImmutable $refreshExpiresAt,
        TokenHash $accessHash,
        DateTimeImmutable $accessExpiresAt,
        DateTimeImmutable $now,
    ): void {
        DB::transaction(function () use ($grant, $refreshHash, $refreshExpiresAt, $accessHash, $accessExpiresAt, $now): void {
            DB::table('auth_oauth_grants')->insert([
                'id' => $grant->id,
                'client_id' => $grant->clientId,
                'user_id' => $grant->userId,
                'resource' => $grant->resource,
                'scope' => $grant->scope,
                'expires_at' => $this->timestamp($refreshExpiresAt),
                'created_at' => $this->timestamp($now),
                'updated_at' => $this->timestamp($now),
            ]);
            $this->insertRefreshToken($grant->id, $refreshHash, $now);
            $this->insertAccessToken($grant->id, $accessHash, $accessExpiresAt, $now);
        }, 3);
    }

    public function rotateRefreshToken(
        TokenHash $currentRefreshHash,
        string $clientId,
        string $resource,
        TokenHash $nextRefreshHash,
        DateTimeImmutable $nextRefreshExpiresAt,
        TokenHash $accessHash,
        DateTimeImmutable $accessExpiresAt,
        DateTimeImmutable $now,
    ): ?OAuthGrant {
        return DB::transaction(function () use (
            $currentRefreshHash,
            $clientId,
            $resource,
            $nextRefreshHash,
            $nextRefreshExpiresAt,
            $accessHash,
            $accessExpiresAt,
            $now,
        ): ?OAuthGrant {
            $row = DB::table('auth_oauth_refresh_tokens')
                ->select([
                    'auth_oauth_refresh_tokens.credential_hash',
                    'auth_oauth_refresh_tokens.consumed_at',
                    'auth_oauth_grants.*',
                ])
                ->join('auth_oauth_grants', 'auth_oauth_grants.id', '=', 'auth_oauth_refresh_tokens.grant_id')
                ->where('auth_oauth_refresh_tokens.credential_hash', strtolower($currentRefreshHash->value))
                ->lockForUpdate()
                ->first();
            if (! $row instanceof stdClass) {
                return null;
            }

            $grantId = (string) $row->id;
            $invalidBinding = (string) $row->client_id !== $clientId || (string) $row->resource !== $resource;
            $expired = new DateTimeImmutable((string) $row->expires_at) <= $now;
            if ($row->consumed_at !== null || $invalidBinding) {
                DB::table('auth_oauth_grants')->where('id', $grantId)->delete();

                return null;
            }
            if ($expired) {
                return null;
            }

            DB::table('auth_oauth_grants')->where('id', $grantId)->update([
                'expires_at' => $this->timestamp($nextRefreshExpiresAt),
                'updated_at' => $this->timestamp($now),
            ]);
            DB::table('auth_oauth_refresh_tokens')
                ->where('credential_hash', strtolower($currentRefreshHash->value))
                ->update(['consumed_at' => $this->timestamp($now)]);
            $this->insertRefreshToken($grantId, $nextRefreshHash, $now);
            $this->insertAccessToken($grantId, $accessHash, $accessExpiresAt, $now);

            return $this->grant($row);
        }, 3);
    }

    public function grantForAccessToken(TokenHash $hash, string $resource, DateTimeImmutable $now): ?OAuthGrant
    {
        $row = DB::table('auth_oauth_grants')
            ->select('auth_oauth_grants.*')
            ->join('auth_oauth_access_tokens', 'auth_oauth_access_tokens.grant_id', '=', 'auth_oauth_grants.id')
            ->where('auth_oauth_access_tokens.credential_hash', strtolower($hash->value))
            ->where('auth_oauth_access_tokens.expires_at', '>', $this->timestamp($now))
            ->where('auth_oauth_grants.resource', $resource)
            ->where('auth_oauth_grants.expires_at', '>', $this->timestamp($now))
            ->first();

        return $row instanceof stdClass ? $this->grant($row) : null;
    }

    public function revoke(TokenHash $hash, string $clientId): void
    {
        DB::transaction(function () use ($hash, $clientId): void {
            $digest = strtolower($hash->value);
            $grantId = DB::table('auth_oauth_refresh_tokens')
                ->join('auth_oauth_grants', 'auth_oauth_grants.id', '=', 'auth_oauth_refresh_tokens.grant_id')
                ->where('auth_oauth_grants.client_id', $clientId)
                ->where('auth_oauth_refresh_tokens.credential_hash', $digest)
                ->value('auth_oauth_grants.id');

            if (! is_string($grantId)) {
                $grantId = DB::table('auth_oauth_access_tokens')
                    ->join('auth_oauth_grants', 'auth_oauth_grants.id', '=', 'auth_oauth_access_tokens.grant_id')
                    ->where('auth_oauth_grants.client_id', $clientId)
                    ->where('auth_oauth_access_tokens.credential_hash', $digest)
                    ->value('auth_oauth_grants.id');
            }

            if (is_string($grantId)) {
                DB::table('auth_oauth_grants')->where('id', $grantId)->delete();
            }
        }, 3);
    }

    public function prune(DateTimeImmutable $now): int
    {
        $timestamp = $this->timestamp($now);

        return DB::transaction(static function () use ($timestamp): int {
            $codes = DB::table('auth_oauth_authorization_codes')->where('expires_at', '<=', $timestamp)->delete();
            $access = DB::table('auth_oauth_access_tokens')->where('expires_at', '<=', $timestamp)->delete();
            $grants = DB::table('auth_oauth_grants')->where('expires_at', '<=', $timestamp)->delete();

            return $codes + $access + $grants;
        }, 3);
    }

    private function insertRefreshToken(
        string $grantId,
        TokenHash $hash,
        DateTimeImmutable $createdAt,
    ): void {
        DB::table('auth_oauth_refresh_tokens')->insert([
            'credential_hash' => strtolower($hash->value),
            'grant_id' => $grantId,
            'consumed_at' => null,
            'created_at' => $this->timestamp($createdAt),
        ]);
    }

    private function insertAccessToken(
        string $grantId,
        TokenHash $hash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): void {
        DB::table('auth_oauth_access_tokens')->insert([
            'credential_hash' => strtolower($hash->value),
            'grant_id' => $grantId,
            'expires_at' => $this->timestamp($expiresAt),
            'created_at' => $this->timestamp($createdAt),
        ]);
    }

    private function grant(stdClass $row): OAuthGrant
    {
        return new OAuthGrant(
            (string) $row->id,
            (string) $row->client_id,
            (int) $row->user_id,
            (string) $row->resource,
            (string) $row->scope,
        );
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
