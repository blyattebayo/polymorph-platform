<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http\Requests;

use Polymorph\Platform\Http\Requests\ApiFormRequest;

class SaveUiConfigRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'document' => ['required', 'array:version,value'],
            'document.version' => ['bail', 'required', 'integer:strict', 'between:1,32767'],
            'document.value' => ['present'],
        ];
    }

    public function validationData(): array
    {
        return ['document' => $this->json()->all()];
    }

    public function version(): int
    {
        return (int) $this->validated('document.version');
    }

    public function document(): string
    {
        return $this->getContent();
    }
}
