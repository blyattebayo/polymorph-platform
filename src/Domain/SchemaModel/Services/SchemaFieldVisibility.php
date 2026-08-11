<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Полевой ACL на пути ЧТЕНИЯ СХЕМЫ.
 *
 * Право schema.{id}.fields.{path}/read решало только судьбу значения в
 * data_json; само описание поля — имя, путь, тип, правила валидации — отдавалось
 * всем, кому открыт эндпоинт схемы. То есть запрет на поле скрывал содержимое,
 * но не сам факт и структуру поля. Здесь это закрывается: чего актору не видно,
 * того нет ни в данных, ни в схеме.
 *
 * Актор берётся из запроса, а не передаётся аргументом: единственные потребители
 * — HTTP-контроллеры схемы, у которых своего понятия актора нет. Fail-closed:
 * нет актора — не видно ни одного поля.
 */
final class SchemaFieldVisibility
{
    public function __construct(
        private readonly FieldAccessService $fieldAccess,
        private readonly AuthenticationContext $auth,
    ) {}

    /**
     * Плоский список полей схемы без тех, что закрыты от актора.
     *
     * @param  Collection<int, Field>  $fields
     * @return Collection<int, Field>
     */
    public function filterFields(int $schemaId, Collection $fields): Collection
    {
        $actor = $this->actor();

        return $fields
            ->filter(fn (Field $field): bool => $this->isReadable($actor, $schemaId, $field))
            ->values();
    }

    /**
     * Дерево полей без закрытых веток.
     *
     * Узел остаётся, если доступен сам либо ведёт к доступному потомку: иначе
     * разрешённое вложенное поле стало бы недостижимым вместе с родителем —
     * ровно так же, как в data_json видимый meta.public_note остаётся вложенным
     * в meta.
     *
     * @param  Collection<int, Field>  $roots
     * @return Collection<int, Field>
     */
    public function filterTree(int $schemaId, Collection $roots): Collection
    {
        $actor = $this->actor();

        return $roots
            ->map(fn (Field $field): ?Field => $this->pruneNode($actor, $schemaId, $field))
            ->filter(static fn (?Field $field): bool => $field instanceof Field)
            ->values();
    }

    private function pruneNode(?User $actor, int $schemaId, Field $field): ?Field
    {
        $children = $field->relationLoaded('children')
            ? $field->getRelation('children')
            : null;

        $keptChildren = $children instanceof Collection
            ? $children
                ->map(fn (Field $child): ?Field => $this->pruneNode($actor, $schemaId, $child))
                ->filter(static fn (?Field $child): bool => $child instanceof Field)
                ->values()
            : null;

        if ($keptChildren instanceof Collection) {
            $field->setRelation('children', $keptChildren);
        }

        $keepsSubtree = $keptChildren instanceof Collection && $keptChildren->isNotEmpty();

        if ($this->isReadable($actor, $schemaId, $field) || $keepsSubtree) {
            return $field;
        }

        return null;
    }

    private function isReadable(?User $actor, int $schemaId, Field $field): bool
    {
        return $this->fieldAccess->isFieldReadable($actor, $schemaId, (string) $field->full_path);
    }

    private function actor(): ?User
    {
        $actor = $this->auth->user();

        return $actor instanceof User ? $actor : null;
    }
}
