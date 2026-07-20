<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging\Contracts;

/**
 * КОНТРАКТ редакции секретов для логов/аудита. Перенесён из Polymorph\PluginSdk\Support
 * при сносе V1 SDK. Реализация — Polymorph\Platform\Support\Logging\PayloadRedactor (биндится в
 * ExtensionsServiceProvider). Контракт ядровый: алгоритм и правила (config/secret_redaction.php)
 * принадлежат ядру.
 */
interface SecretRedactor
{
    public const REDACTED = '[redacted]';

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<mixed, mixed>
     */
    public function redact(array $payload): array;

    public function redactString(string $value): string;
}
