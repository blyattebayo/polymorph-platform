<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Sdk\Data\FieldName as SdkFieldName;

/** One validated local schema-tree segment. */
final readonly class FieldName
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        try {
            $name = SdkFieldName::from($value);
        } catch (\InvalidArgumentException $exception) {
            throw DataPlatformBadRequest::because(
                'invalid_field_name',
                $exception->getMessage(),
                ['name' => $value],
            );
        }

        return new self($name->value);
    }
}
