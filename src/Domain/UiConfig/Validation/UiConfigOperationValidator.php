<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Validation;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDelete;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigWrite;
use Polymorph\Platform\Support\Errors\ValidationFailedException;

/**
 * Перевод сырой операции в проверенную команду: дальше, к сервису, уходит только
 * типизированный {@see UiConfigWrite} или {@see UiConfigDelete}.
 *
 * Правила у операции одни и те же, кто бы её ни принёс, поэтому живут здесь, а не
 * во FormRequest. Формой ошибки валидатор при этом не распоряжается: её знает
 * {@see ValidationFailedException}, одна на весь бэкенд.
 */
final class UiConfigOperationValidator
{
    public function __construct(
        private readonly ValidationFactory $validation,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateWrite(array $payload): UiConfigWrite
    {
        $validated = $this->validate($payload, [
            ...$this->addressRules(),
            'version' => ['bail', 'required', 'integer:strict', 'between:1,32767'],
            'value' => ['present'],
        ]);

        return new UiConfigWrite(
            key: (string) $validated['key'],
            domain: UiConfigDomain::from((string) $validated['domain']),
            revision: (int) $validated['revision'],
            version: (int) $validated['version'],
            value: $validated['value'] ?? null,
        );
    }

    /**
     * Удаление адресуется тем же способом и так же условно, только документа у
     * него нет.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateDelete(array $payload): UiConfigDelete
    {
        $validated = $this->validate($payload, $this->addressRules());

        return new UiConfigDelete(
            key: (string) $validated['key'],
            domain: UiConfigDomain::from((string) $validated['domain']),
            revision: (int) $validated['revision'],
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function addressRules(): array
    {
        return [
            // Вид конфигурации закодирован в ключе, поэтому ключ проверяется
            // только как непрозрачная строка допустимого алфавита.
            'key' => ['bail', 'required', 'string', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'domain' => ['bail', 'required', Rule::enum(UiConfigDomain::class)],
            // Ответ читает JavaScript-клиент, поэтому opaque token обязан
            // оставаться в диапазоне его точных целых.
            'revision' => ['bail', 'required', 'integer', 'between:0,9007199254740991'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $payload, array $rules): array
    {
        $validator = $this->validation->make($payload, $rules);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors()->messages());
        }

        return $validator->validated();
    }
}
