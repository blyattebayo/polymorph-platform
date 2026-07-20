<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Http\Requests\AuthenticatedRequest;
use Polymorph\Platform\Support\Validation\Rules\ObjectLikeArray;

class UpdateRecordRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'record_definition_id' => 'prohibited',
            RecordPayloadPathRegistry::ROOT_KEY => [
                'sometimes',
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
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function dataJson(): array
    {
        return $this->validated(RecordPayloadPathRegistry::ROOT_KEY) ?? [];
    }
}
