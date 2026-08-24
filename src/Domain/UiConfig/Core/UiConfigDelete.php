<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

/**
 * Проверенная операция удаления: адрес и заявленная ревизия.
 *
 * Отдельный тип, а не {@see UiConfigWrite} без документа: у удаления нет ни
 * значения, ни версии формата, и подставлять им нули значило бы носить поля,
 * которые ничего не значат — версия 0 к тому же запрещена правилами записи.
 */
final readonly class UiConfigDelete
{
    public function __construct(
        public string $key,
        public UiConfigDomain $domain,
        public int $revision,
    ) {}
}
