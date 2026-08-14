<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;
use Throwable;

final class DateTimeFieldTypeHandler extends ComparableFieldTypeHandler
{
    public function type(): string
    {
        return 'datetime';
    }

    protected function normalizeOne(mixed $value, FieldDefinition $field, string $occurrence): string
    {
        if (! $value instanceof DateTimeInterface && (! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1)) {
            throw DataValidationException::one('type', 'Expected an ISO-8601 datetime.', $field->path, $occurrence);
        }

        try {
            if ($value instanceof DateTimeInterface) {
                $date = DateTimeImmutable::createFromInterface($value);
            } else {
                $date = new DateTimeImmutable($value);
            }
        } catch (Throwable) {
            throw DataValidationException::one('type', 'Expected an ISO-8601 datetime.', $field->path, $occurrence);
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    protected function validateOne(mixed $value, FieldDefinition $field, string $occurrence): void
    {
        if (! is_string($value)) {
            throw DataValidationException::one('type', 'Expected an ISO-8601 datetime.', $field->path, $occurrence);
        }
    }
}
