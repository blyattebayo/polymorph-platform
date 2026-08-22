<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Http\Requests;

use Polymorph\Platform\Domain\UiConfig\Http\Requests\SaveUiConfigRequest;

final class EntryViewConfigRequest extends SaveUiConfigRequest
{
    public function rules(): array
    {
        $routeRules = [
            'recordDefinition' => ['required', 'integer', 'min:1'],
            'schema' => ['required', 'integer', 'min:1'],
        ];

        return $this->isMethod('PUT')
            ? [...$routeRules, ...parent::rules()]
            : $routeRules;
    }

    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'recordDefinition' => $this->route('recordDefinition'),
            'schema' => $this->route('schema'),
        ];
    }
}
