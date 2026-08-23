<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http\Requests;

class SaveUiConfigRequest extends UiConfigWriteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version' => ['bail', 'required', 'integer:strict', 'between:1,32767'],
            'value' => ['present'],
        ];
    }

    public function version(): int
    {
        return (int) $this->validated('version');
    }

    /**
     * Документ собирается из версии формата и значения — двух полей операции, а
     * не из всего тела: адресация в хранилище не попадает. Порядок ключей внутри
     * значения сохраняет json_decode, а на чтении документ всё равно проходит
     * через PHP, см. StoresRawDocument.
     */
    public function document(): string
    {
        return (string) json_encode(
            ['version' => $this->version(), 'value' => $this->validated('value')],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
