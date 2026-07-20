<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Requests;

use Polymorph\Platform\Support\Validation\ValidationRules;
use Polymorph\Platform\Support\Validation\Rules\ObjectLikeArray;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchemaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ValidationRules::slug(),
            'description' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],

            'fields' => ['sometimes', 'array'],
            'fields.upsert' => ['sometimes', 'array'],
            'fields.delete' => ['sometimes', 'array'],
            'fields.delete.*' => ['integer', 'distinct'],

            'fields.upsert.*.id' => ['sometimes', 'integer'],
            'fields.upsert.*.name' => ValidationRules::slug(required: false),
            'fields.upsert.*.type' => ['sometimes'],
            'fields.upsert.*.cardinality' => ['sometimes'],
            'fields.upsert.*.parent_id' => ['sometimes', 'nullable', 'integer'],
            'fields.upsert.*.is_indexed' => ['sometimes', 'boolean'],
            'fields.upsert.*.is_system' => ['sometimes', 'boolean'],
            'fields.upsert.*.validation_rules' => [
                'sometimes',
                'nullable',
                'array',
                new ObjectLikeArray(
                    message: 'The :attribute must be an object-like map, list format is not supported.'
                ),
            ],
            'fields.upsert.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'fields.upsert.*.metadata' => ['sometimes', 'nullable', 'array'],
            'fields.upsert.*.constraints' => ['sometimes', 'nullable', 'array'],
            'fields.upsert.*.constraints.allowed_record_definition_id' => ['nullable', 'integer', 'exists:record_definitions,id'],
            'fields.upsert.*.constraints.allowed_mimes' => ['nullable', 'array', 'min:1'],
            'fields.upsert.*.constraints.allowed_mimes.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fields.upsert.*.validation_rules.array' => 'The :attribute must be an object-like map.',
        ];
    }
}
