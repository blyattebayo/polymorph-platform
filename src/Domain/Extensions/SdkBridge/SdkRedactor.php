<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\SdkBridge;

use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;
use Polymorph\Sdk\Logging\Redactor;

/**
 * Host-адаптер {@see Redactor} поверх единой политики маскирования ядра
 * ({@see SecretRedactor} → PayloadRedactor). Так расширения не катают свои
 * редакторы: правка политики ядра (новый паттерн утечки/чувствительный ключ)
 * автоматически достаётся всем расширениям.
 */
final class SdkRedactor implements Redactor
{
    public function __construct(private readonly SecretRedactor $redactor) {}

    public function redact(array $payload): array
    {
        return $this->redactor->redact($payload);
    }

    public function redactString(string $value): string
    {
        return $this->redactor->redactString($value);
    }
}
