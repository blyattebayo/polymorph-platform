<?php

declare(strict_types=1);

/**
 * Политика редакции секретов — собственность ЯДРА. Реализуется
 * Polymorph\Platform\Support\Logging\PayloadRedactor (контракт Polymorph\Platform\Support\Logging\Contracts\SecretRedactor).
 *
 * sensitive_key_pattern — regex имён ключей, значение которых маскируется целиком.
 * Должен совпадать с SecretRedactor::DEFAULT_SENSITIVE_KEY_PATTERN (standalone-фолбэк).
 */
return [
    'sensitive_key_pattern' => '/(^|[_-])(password|passwd|secret|token|authorization|cookie|api[_-]?key|private[_-]?key|code[_-]?verifier|device[_-]?code|user[_-]?code)([_-]|$)/i',
];
