<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Requests;

use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\CreateRecordDefinitionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRecordDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('record_definitions', 'name'),
            ],
            'schema_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('schemas', 'id'),
            ],
            'display_template' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function toData(): CreateRecordDefinitionData
    {
        $validated = $this->validated();

        return new CreateRecordDefinitionData(
            name: (string) $validated['name'],
            schemaId: isset($validated['schema_id']) ? (int) $validated['schema_id'] : null,
            displayTemplate: isset($validated['display_template']) ? (string) $validated['display_template'] : null,
        );
    }
}
