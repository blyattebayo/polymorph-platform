<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\RecordDefinitions\Core\ValueObjects\UpdateRecordDefinitionData;

final class UpdateRecordDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $recordDefinitionId = (int) $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('record_definitions', 'name')->ignore($recordDefinitionId),
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

    public function toData(): UpdateRecordDefinitionData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateRecordDefinitionData::fromValidated($validated);
    }
}
