<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;

/**
 * Редакция секретов в контексте логов. Делегирует контракту {@see SecretRedactor},
 * реализацию которого предоставляет ядро (PayloadRedactor, биндинг в ExtensionsServiceProvider)
 * — те же правила, что у аудита плагинов.
 */
final class LogContextRedactor
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        return $this->redactor->redact($context);
    }
}
