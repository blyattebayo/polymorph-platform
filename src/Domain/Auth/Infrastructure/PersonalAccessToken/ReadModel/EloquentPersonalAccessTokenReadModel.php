<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\ReadModel;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use JsonException;
use Polymorph\Platform\Domain\Auth\Application\Contracts\Clock;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenOwnerView;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\Data\PersonalAccessTokenView;
use Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken\PersonalAccessTokenReadModel;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenScopes;
use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenStatus;
use Polymorph\Platform\Domain\Auth\Domain\ValueObjects\UserId;
use Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Persistence\PersonalAccessTokenRecord;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageMeta;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;
use stdClass;

final readonly class EloquentPersonalAccessTokenReadModel implements PersonalAccessTokenReadModel
{
    public function __construct(private Clock $clock) {}

    public function forUser(UserId $userId): array
    {
        return $this->baseQuery(includeOwner: false)
            ->where('pat.user_id', $userId->value)
            ->orderByDesc('pat.issued_at')
            ->orderByDesc('pat.id')
            ->get()
            ->map(fn (stdClass $row): PersonalAccessTokenView => $this->map($row, includeOwner: false))
            ->all();
    }

    public function all(PageRequest $page): PageResult
    {
        $query = $this->baseQuery(includeOwner: true);

        $paginator = $query
            ->orderByDesc('pat.issued_at')
            ->orderByDesc('pat.id')
            ->paginate(perPage: $page->perPage, page: $page->page);

        return new PageResult(
            items: array_map(
                fn (stdClass $row): PersonalAccessTokenView => $this->map($row, includeOwner: true),
                $paginator->items(),
            ),
            meta: new PageMeta(
                currentPage: (int) $paginator->currentPage(),
                lastPage: (int) $paginator->lastPage(),
                perPage: (int) $paginator->perPage(),
                total: (int) $paginator->total(),
            ),
        );
    }

    private function baseQuery(bool $includeOwner): Builder
    {
        $query = DB::table(PersonalAccessTokenRecord::TABLE.' as pat')->select([
            'pat.id',
            'pat.name',
            'pat.display_hint',
            'pat.scopes',
            'pat.issued_at',
            'pat.expires_at',
            'pat.revoked_at',
            'pat.last_used_at',
        ]);

        if ($includeOwner) {
            $query
                ->join('users as owner', 'owner.id', '=', 'pat.user_id')
                ->addSelect([
                    'owner.id as owner_id',
                    'owner.name as owner_name',
                    'owner.email as owner_email',
                ]);
        }

        return $query;
    }

    private function map(stdClass $row, bool $includeOwner): PersonalAccessTokenView
    {
        $issuedAt = $this->date($row->issued_at);
        $expiresAt = $this->date($row->expires_at);
        $revokedAt = $this->nullableDate($row->revoked_at);
        $now = $this->clock->now();

        $status = PersonalAccessTokenStatus::at($issuedAt, $expiresAt, $revokedAt, $now);

        return new PersonalAccessTokenView(
            id: (string) $row->id,
            name: (string) $row->name,
            displayHint: (string) $row->display_hint,
            scopes: PersonalAccessTokenScopes::fromArray($this->scopes($row->scopes))->toArray(),
            status: $status,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            lastUsedAt: $this->nullableDate($row->last_used_at),
            owner: $includeOwner ? new PersonalAccessTokenOwnerView(
                id: (int) $row->owner_id,
                name: (string) $row->owner_name,
                email: (string) $row->owner_email,
            ) : null,
        );
    }

    /** @return list<mixed> */
    private function scopes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new JsonException('Persisted personal access token scopes must be an array.');
        }

        return array_values($decoded);
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value);
    }

    private function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->date($value);
    }
}
