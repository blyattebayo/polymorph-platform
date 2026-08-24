<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Infrastructure;

use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;
use Polymorph\Platform\PipelineCore\Locking\LockKey;

/**
 * Адрес ячейки хранения: у общей конфигурации это ключ, у личной — ключ и автор.
 *
 * Собрать адрес можно только одним из двух способов, поэтому личная ячейка без
 * автора непредставима: раньше автор был обычным нулевым полем, и чтение личной
 * конфигурации без автора спокойно уходило в запрос `author_id IS NULL`.
 *
 * Идентичность описана один раз: из неё выводится и запрос, и набор полей
 * записи, и ключ лока.
 */
final readonly class UiConfigSlot
{
    private function __construct(
        public string $key,
        public UiConfigDomain $domain,
        public ?int $authorId,
    ) {}

    /** Общая ячейка: одна на ключ, автор в адрес не входит. */
    public static function global(string $key): self
    {
        return new self($key, UiConfigDomain::GLOBAL, null);
    }

    /** Личная ячейка: автор — часть адреса, поэтому обязателен. */
    public static function personal(string $key, int $authorId): self
    {
        return new self($key, UiConfigDomain::USER, $authorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        $identity = ['key' => $this->key, 'domain' => $this->domain->value];

        return $this->domain->isGlobal() ? $identity : [...$identity, 'author_id' => $this->authorId];
    }

    public function lock(): LockKey
    {
        return new LockKey(
            'ui-config',
            $this->domain->value,
            $this->domain->isGlobal() ? $this->key : $this->key.'/'.$this->authorId,
        );
    }
}
