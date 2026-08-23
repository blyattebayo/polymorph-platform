<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Models\Concerns;

/**
 * Документ UI-конфига хранится в исходном виде, без Eloquent json cast.
 */
trait StoresRawDocument
{
    public function rawDocument(): string
    {
        return (string) $this->getRawOriginal('document');
    }

    public function value(): mixed
    {
        /** @var array{value: mixed} $document */
        $document = json_decode($this->rawDocument(), true, flags: JSON_THROW_ON_ERROR);

        return $document['value'];
    }

    public function version(): int
    {
        return (int) $this->getAttribute('version');
    }

    /**
     * Номер состояния ячейки. У ещё не сохранённой модели — 0, поэтому «конфига
     * нет» и «конфиг нулевой ревизии» для клиента одно и то же состояние.
     */
    public function revision(): int
    {
        return (int) $this->getAttribute('revision');
    }
}
