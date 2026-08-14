<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields\Handlers;

final class TextFieldTypeHandler extends StringFieldTypeHandler
{
    public function type(): string
    {
        return 'text';
    }
}
