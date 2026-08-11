<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Repositories;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\Auth\Application\Contracts\SessionRepository;
use Polymorph\Platform\Domain\Auth\Application\Models\AuthenticatedSession;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\ClientMetadata;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\SessionId;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\TokenHash;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use stdClass;

final class EloquentSessionRepository implements SessionRepository
{
    public function add(Session $session): void
    {
        DB::table('auth_sessions')->insert([
            'id' => (string) $session->id(),
            'credential_hash' => $session->credentialHash()->value,
            'user_id' => $session->userId()->value,
            'created_at' => $this->timestamp($session->createdAt()),
            'expires_at' => $this->timestamp($session->expiresAt()),
            'ip' => $session->client()->ip,
            'user_agent' => $session->client()->userAgent,
        ]);
    }

    public function findAuthenticated(TokenHash $credentialHash, DateTimeImmutable $now): ?AuthenticatedSession
    {
        $user = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.status',
                'users.created_at',
                'users.updated_at',
                'auth_sessions.id as authenticated_session_id',
            ])
            ->join('auth_sessions', 'auth_sessions.user_id', '=', 'users.id')
            ->where('auth_sessions.credential_hash', $credentialHash->value)
            ->where('auth_sessions.expires_at', '>', $this->timestamp($now))
            ->where('users.status', User::STATUS_ACTIVE)
            ->first();

        if (! $user instanceof User) {
            return null;
        }

        return new AuthenticatedSession(
            new SessionId((string) $user->getAttribute('authenticated_session_id')),
            $user,
        );
    }

    public function findForUpdate(SessionId $id): ?Session
    {
        return $this->map(DB::table('auth_sessions')->where('id', (string) $id)->lockForUpdate()->first());
    }

    public function activeForUserForUpdate(UserId $userId, DateTimeImmutable $now): array
    {
        return $this->mapMany($this->activeQuery($userId, $now)->lockForUpdate()->get()->all());
    }

    public function activeForUser(UserId $userId, DateTimeImmutable $now): array
    {
        return $this->mapMany($this->activeQuery($userId, $now)->get()->all());
    }

    public function delete(SessionId $id): void
    {
        DB::table('auth_sessions')->where('id', (string) $id)->delete();
    }

    public function deleteForUser(UserId $userId): void
    {
        DB::table('auth_sessions')->where('user_id', $userId->value)->delete();
    }

    private function activeQuery(UserId $userId, DateTimeImmutable $now): Builder
    {
        return DB::table('auth_sessions')
            ->where('user_id', $userId->value)
            ->where('expires_at', '>', $this->timestamp($now))
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** @param list<object> $rows @return list<Session> */
    private function mapMany(array $rows): array
    {
        return array_values(array_map(fn (object $row): Session => $this->map($row), $rows));
    }

    private function map(?object $row): ?Session
    {
        if (! $row instanceof stdClass) {
            return null;
        }

        return new Session(
            new SessionId((string) $row->id),
            new UserId((int) $row->user_id),
            new TokenHash((string) $row->credential_hash),
            $this->date($row->created_at),
            $this->date($row->expires_at),
            new ClientMetadata(
                $row->ip === null ? null : (string) $row->ip,
                $row->user_agent === null ? null : (string) $row->user_agent,
            ),
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
