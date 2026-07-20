<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

/**
 * Сервис для управления ограничениями полей (Reference и Media constraints).
 *
 * Идемпотентен и устойчив к неполному payload: отсутствующие ключи трактуются
 * как «ограничения нет» и приводят к очистке, а не к записи мусора/падению.
 * Также чистит осиротевшие ограничения при смене типа поля (REF↔MEDIA↔прочее).
 */
class ConstraintManager
{
    /**
     * Синхронизировать ограничения для поля.
     *
     * @param array{allowed_record_definition_id?: int|null, allowed_mimes?: string[]|null} $constraints
     */
    public function syncConstraints(Field $field, array $constraints): void
    {
        // REF-ограничение актуально только для REF-поля; иначе чистим осиротевшее.
        if ($field->type === FieldType::REF) {
            $this->syncRefConstraint($field, $constraints);
        } else {
            $field->refConstraint()->delete();
        }

        // MEDIA-ограничения актуальны только для MEDIA-поля.
        if ($field->type === FieldType::MEDIA) {
            $this->syncMediaConstraints($field, $constraints);
        } else {
            $field->mediaConstraints()->delete();
        }
    }

    /**
     * @param array{allowed_record_definition_id?: int|null} $constraints
     */
    private function syncRefConstraint(Field $field, array $constraints): void
    {
        $recordDefinitionId = isset($constraints['allowed_record_definition_id'])
            ? (int) $constraints['allowed_record_definition_id']
            : 0;

        // Нет валидного целевого определения — ограничения нет (любое допустимо): чистим.
        if ($recordDefinitionId <= 0) {
            $field->refConstraint()->delete();

            return;
        }

        // updateOrCreate, чтобы Observer сработал при изменении.
        $field->refConstraint()->updateOrCreate(
            ['field_id' => $field->id],
            ['allowed_record_definition_id' => $recordDefinitionId],
        );
    }

    /**
     * @param array{allowed_mimes?: string[]|null} $constraints
     */
    private function syncMediaConstraints(Field $field, array $constraints): void
    {
        $mimeTypes = $constraints['allowed_mimes'] ?? [];

        if (! is_array($mimeTypes)) {
            $mimeTypes = [];
        }

        // Полная замена набора: удалить старые, создать новые.
        $field->mediaConstraints()->delete();

        foreach ($mimeTypes as $mime) {
            $field->mediaConstraints()->create([
                'allowed_mime' => $mime,
            ]);
        }
    }
}
