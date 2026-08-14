<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use Polymorph\Platform\Domain\DataPlatform\Fields\DependencySet;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\ResolvedDependencies;
use Polymorph\Platform\Domain\DataPlatform\Projection\FieldProjectionChanges;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaProcessingState;

final class MediaFieldTypeHandler extends EdgeFieldTypeHandler
{
    public function validateSchema(FieldDefinition $field): void
    {
        parent::validateSchema($field);
        if (array_key_exists('require_alt', $field->constraints) && ! is_bool($field->constraints['require_alt'])) {
            throw DataValidationException::one('invalid_schema_constraint', "Constraint 'require_alt' must be boolean.", $field->path);
        }
        $mimes = $field->constraints['allowed_mimes'] ?? null;
        if ($mimes !== null && (! is_array($mimes) || $mimes === [] || array_filter(
            $mimes,
            static fn (mixed $mime): bool => ! is_string($mime)
                || preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/(?:\*|[a-z0-9][a-z0-9!#$&^_.+-]*)$~iD', $mime) !== 1,
        ) !== [])) {
            throw DataValidationException::one('invalid_schema_constraint', 'allowed_mimes must contain valid MIME types or type/* patterns.', $field->path);
        }
        $this->validateEnumConstraint($field, 'allowed_kinds', array_column(MediaKind::cases(), 'value'));
        $this->validateEnumConstraint($field, 'allowed_processing_states', array_column(MediaProcessingState::cases(), 'value'));
        $this->assertNonNegativeIntegerRange($field, 'max_size_bytes');
        foreach ([['min_width', 'max_width'], ['min_height', 'max_height'], ['min_duration_ms', 'max_duration_ms']] as [$minimum, $maximum]) {
            $this->assertNonNegativeIntegerRange($field, $minimum, $maximum);
        }
    }

    public function type(): string
    {
        return 'media';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): array
    {
        $attachment = is_string($value) ? ['id' => $value] : $value;
        if (! is_array($attachment) || ! is_string($attachment['id'] ?? null) || trim($attachment['id']) === '') {
            throw DataValidationException::one('media_id', 'Expected a media ID or attachment object.', $field->path, $occurrence);
        }
        foreach (['alt', 'caption'] as $textField) {
            if (array_key_exists($textField, $attachment)
                && $attachment[$textField] !== null
                && ! is_string($attachment[$textField])) {
                throw DataValidationException::one('type', "Media {$textField} must be a string.", $field->path, $occurrence);
            }
        }

        return array_filter([
            'id' => trim($attachment['id']),
            'alt' => isset($attachment['alt']) ? trim($attachment['alt']) : null,
            'caption' => isset($attachment['caption']) ? trim($attachment['caption']) : null,
        ], static fn (mixed $item): bool => $item !== null);
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_array($value) || ! is_string($value['id'] ?? null) || $value['id'] === '') {
            throw DataValidationException::one('media_id', 'Expected a media attachment object.', $field->path, $occurrence);
        }
        foreach (['alt', 'caption'] as $textField) {
            if (array_key_exists($textField, $value)
                && $value[$textField] !== null
                && ! is_string($value[$textField])) {
                throw DataValidationException::one('type', "Media {$textField} must be a string.", $field->path, $occurrence);
            }
        }
        $alt = $value['alt'] ?? '';
        if (($field->constraints['require_alt'] ?? false) === true && (! is_string($alt) || trim($alt) === '')) {
            throw DataValidationException::one('media_alt', 'Alternative text is required.', $field->path, $occurrence);
        }
    }

    public function collectBatchDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        DependencySet $dependencies,
    ): void {
        foreach ($this->values($value, $field) as $attachment) {
            $dependencies->addMedia((string) $attachment['id']);
        }
    }

    public function validateResolvedDependencies(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
        ResolvedDependencies $dependencies,
    ): void {
        foreach ($this->values($value, $field) as $index => $attachment) {
            $id = (string) $attachment['id'];
            $media = $dependencies->media[$id] ?? null;
            $itemOccurrence = $this->itemOccurrence($field, $occurrence, $index);
            if ($media === null) {
                throw DataValidationException::one('media_missing', 'Media asset does not exist.', $field->path, $itemOccurrence, ['media_id' => $id]);
            }
            if (($media['deleted_at'] ?? null) !== null) {
                throw DataValidationException::one('media_deleted', 'Media asset is deleted.', $field->path, $itemOccurrence, ['media_id' => $id]);
            }

            $allowedKinds = $field->constraints['allowed_kinds'] ?? null;
            if (is_array($allowedKinds)
                && ! in_array((string) ($media['kind'] ?? 'document'), $allowedKinds, true)) {
                throw DataValidationException::one('media_kind', 'Media kind is not allowed.', $field->path, $itemOccurrence, [
                    'kind' => (string) ($media['kind'] ?? 'document'),
                ]);
            }

            $this->validateMime($media, $field, $itemOccurrence);
            $maxSize = $field->constraints['max_size_bytes'] ?? null;
            if (is_int($maxSize) && (int) ($media['size_bytes'] ?? 0) > $maxSize) {
                throw DataValidationException::one('media_size', 'Media asset exceeds the maximum size.', $field->path, $itemOccurrence);
            }

            $states = $field->constraints['allowed_processing_states'] ?? null;
            if (is_array($states) && ! in_array((string) ($media['processing_state'] ?? 'ready'), $states, true)) {
                throw DataValidationException::one('media_state', 'Media processing state is not allowed.', $field->path, $itemOccurrence);
            }

            $minWidth = $field->constraints['min_width'] ?? null;
            $maxWidth = $field->constraints['max_width'] ?? null;
            $minHeight = $field->constraints['min_height'] ?? null;
            $maxHeight = $field->constraints['max_height'] ?? null;
            $width = $media['width'] ?? null;
            $height = $media['height'] ?? null;
            if ((is_int($minWidth) && (! is_numeric($width) || (int) $width < $minWidth))
                || (is_int($maxWidth) && is_numeric($width) && (int) $width > $maxWidth)
                || (is_int($minHeight) && (! is_numeric($height) || (int) $height < $minHeight))
                || (is_int($maxHeight) && is_numeric($height) && (int) $height > $maxHeight)) {
                throw DataValidationException::one('media_dimensions', 'Media dimensions are not allowed.', $field->path, $itemOccurrence);
            }

            $minDuration = $field->constraints['min_duration_ms'] ?? null;
            $maxDuration = $field->constraints['max_duration_ms'] ?? null;
            $duration = $media['duration_ms'] ?? null;
            if ((is_int($minDuration) && (! is_numeric($duration) || (int) $duration < $minDuration))
                || (is_int($maxDuration) && is_numeric($duration) && (int) $duration > $maxDuration)) {
                throw DataValidationException::one('media_duration', 'Media duration is not allowed.', $field->path, $itemOccurrence);
            }
        }
    }

    public function buildProjectionChanges(
        mixed $value,
        FieldDefinition $field,
        string $occurrence,
    ): FieldProjectionChanges {
        $edges = $this->edgeRows(
            $value,
            $field,
            $occurrence,
            static fn (mixed $attachment): array => [
                'media_id' => (string) $attachment['id'],
                'attachment' => $attachment,
            ],
        );

        return new FieldProjectionChanges(mediaEdges: $edges);
    }

    protected function edgeStrategy(): string
    {
        return 'media_edge';
    }

    protected function unsupportedOperatorReason(): string
    {
        return 'unsupported_media_operator';
    }

    protected function operatorSubject(): string
    {
        return 'media';
    }

    /** @param array<string, mixed> $media */
    private function validateMime(array $media, FieldDefinition $field, string $occurrence): void
    {
        $mime = (string) ($media['mime'] ?? '');
        $allowed = $field->constraints['allowed_mimes'] ?? null;
        if (! is_array($allowed) || $allowed === []) {
            return;
        }

        foreach ($allowed as $pattern) {
            if ($pattern === $mime || (is_string($pattern) && str_ends_with($pattern, '/*') && str_starts_with($mime, substr($pattern, 0, -1)))) {
                return;
            }
        }

        throw DataValidationException::one('media_mime', "MIME type '{$mime}' is not allowed.", $field->path, $occurrence);
    }

    /** @param list<string> $allowed */
    private function validateEnumConstraint(FieldDefinition $field, string $name, array $allowed): void
    {
        $value = $field->constraints[$name] ?? null;
        if ($value !== null && (! is_array($value) || $value === [] || array_filter(
            $value,
            static fn (mixed $item): bool => ! is_string($item) || ! in_array($item, $allowed, true),
        ) !== [])) {
            throw DataValidationException::one('invalid_schema_constraint', "Constraint '{$name}' contains unsupported values.", $field->path);
        }
    }
}
