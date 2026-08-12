<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/** Builds the complete visible field tree for the current HTTP actor. */
final class SchemaFieldVisibility
{
    public function __construct(
        private readonly FieldAccessService $fieldAccess,
        private readonly AuthenticationContext $auth,
    ) {}

    /**
     * @param Collection<int, Field> $fields flat, complete schema field set
     * @return Collection<int, Field> visible roots with recursively loaded children
     */
    public function visibleTree(int $schemaId, Collection $fields): Collection
    {
        $childrenByParent = $fields->groupBy(
            static fn (Field $field): int => $field->parent_id === null ? 0 : (int) $field->parent_id,
        );

        foreach ($fields as $field) {
            $field->setRelation('children', ($childrenByParent->get((int) $field->id) ?? collect())->values());
        }

        $actor = $this->auth->user();
        $user = $actor instanceof User ? $actor : null;

        return ($childrenByParent->get(0) ?? collect())
            ->map(fn (Field $field): ?Field => $this->prune($user, $schemaId, $field))
            ->filter(static fn (?Field $field): bool => $field instanceof Field)
            ->values();
    }

    private function prune(?User $actor, int $schemaId, Field $field): ?Field
    {
        /** @var Collection<int, Field> $children */
        $children = $field->getRelation('children');
        $visibleChildren = $children
            ->map(fn (Field $child): ?Field => $this->prune($actor, $schemaId, $child))
            ->filter(static fn (?Field $child): bool => $child instanceof Field)
            ->values();
        $field->setRelation('children', $visibleChildren);

        if ($visibleChildren->isNotEmpty()
            || $this->fieldAccess->isFieldReadable($actor, $schemaId, (string) $field->full_path)) {
            return $field;
        }

        return null;
    }
}
