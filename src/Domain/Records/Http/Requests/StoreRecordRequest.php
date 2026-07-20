<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Requests;

use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Http\Requests\AuthenticatedRequest;
use Polymorph\Platform\Support\Validation\Rules\ObjectLikeArray;

class StoreRecordRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'record_definition_id' => 'required|integer',
            RecordPayloadPathRegistry::ROOT_KEY => [
                'nullable',
                'array',
                new ObjectLikeArray(allowEmptyList: true),
            ],
        ];
    }

    /**
        * Получить кастомные сообщения для ошибок валидации.
     *
        * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'record_definition_id.required' => 'The record definition id field is required.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dataJson(): array
    {
        return $this->validated(RecordPayloadPathRegistry::ROOT_KEY) ?? [];
    }
}

