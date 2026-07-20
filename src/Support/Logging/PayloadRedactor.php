<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Polymorph\Platform\Support\Logging\Contracts\SecretRedactor;

/**
 * Ядровая реализация контракта {@see SecretRedactor}. Владеет и алгоритмом обхода,
 * и политикой: чувствительные КЛЮЧИ берутся из config/secret_redaction.php (ядро —
 * источник правды), value-shape детекция (Bearer/Basic/JWT/длинные токены) — универсальна.
 * Биндится на контракт в ExtensionsServiceProvider и инъектится ядру и плагинам.
 */
final class PayloadRedactor implements SecretRedactor
{
    private const MAX_DEPTH = 8;

    private readonly string $sensitiveKeyPattern;

    public function __construct(?string $sensitiveKeyPattern = null)
    {
        $configured = $sensitiveKeyPattern ?? config('secret_redaction.sensitive_key_pattern');
        if (! is_string($configured) || $configured === '') {
            throw new \RuntimeException('Missing config/secret_redaction.php (sensitive_key_pattern) — core must define the redaction policy.');
        }

        $this->sensitiveKeyPattern = $configured;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     * @return array<mixed, mixed>
     */
    public function redact(array $payload): array
    {
        return $this->redactArray($payload, 0);
    }

    public function redactString(string $value): string
    {
        $patterns = [
            // Схема сохраняется (видно, что был токен), вырезается только секрет.
            '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i' => 'Bearer ' . self::REDACTED,
            '/Basic\s+[A-Za-z0-9+\/=]+/i' => 'Basic ' . self::REDACTED,
            // JWT: header.payload.signature.
            '/eyJ[A-Za-z0-9._\-]{20,}/' => self::REDACTED,
            // Длинные непрерывные токены: hex, base64 и base64url ('-'/'_'),
            // напр. GitHub PAT 'ghp_…' (40 символов) — их не ловят паттерны выше.
            '/[A-Za-z0-9][A-Za-z0-9._~+\/=-]{39,}={0,2}/' => self::REDACTED,
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    /**
     * @param  array<mixed, mixed>  $values
     * @return array<mixed, mixed>
     */
    private function redactArray(array $values, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ['_truncated' => true];
        }

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redactArray($value, $depth + 1);

                continue;
            }

            if (is_string($value)) {
                $values[$key] = $this->redactString($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        return preg_match($this->sensitiveKeyPattern, $key) === 1;
    }
}
