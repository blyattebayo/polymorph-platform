<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http\Requests;

use Illuminate\Validation\Rule;
use Polymorph\Platform\Domain\UiConfig\Core\ConfigNamespace;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

/**
 * Запись адресуется целиком телом запроса: вид, ключ и заявленная ревизия — это
 * данные операции, а не путь к ней. Путь остаётся только у чтения.
 *
 * Любая запись условна: клиент заявляет ревизию, которую прочитал, и запись
 * применяется только к этому состоянию. Отсутствующий конфиг — ревизия 0.
 */
class UiConfigWriteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'namespace' => ['bail', 'required', Rule::enum(ConfigNamespace::class)],
            'key' => ['bail', 'required', 'string', 'max:191', 'regex:/^[A-Za-z0-9._:-]+$/'],
            // Ответ читает JavaScript-клиент, поэтому opaque token обязан
            // оставаться в диапазоне его точных целых.
            'revision' => ['bail', 'required', 'integer', 'between:0,9007199254740991'],
        ];
    }

    /**
     * Операция читается только из JSON-тела и никогда не достраивается из query
     * или формы.
     */
    public function validationData(): array
    {
        return $this->json()->all();
    }

    public function configNamespace(): ConfigNamespace
    {
        return ConfigNamespace::from((string) $this->validated('namespace'));
    }

    public function key(): string
    {
        return (string) $this->validated('key');
    }

    public function revision(): int
    {
        return (int) $this->validated('revision');
    }
}
