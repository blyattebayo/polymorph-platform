<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Идентификатор трассировки текущего запроса — один и тот же для лога и для тела ответа.
 *
 * Берётся из входящего заголовка, если клиент или граничный прокси его прислал, иначе
 * генерируется. Генерация обязательна: без неё поле оставалось бы пустым на всех
 * запросах, кроме тех, где кто-то снаружи уже проставил заголовок, — а значит
 * сопоставить жалобу пользователя с логом было бы нечем.
 *
 * Биндится как scoped: значение живёт ровно один запрос (в т.ч. под Octane).
 */
final class TraceId
{
    /**
     * Заголовки в порядке доверия. X-Trace-ID — свой, X-Request-ID/X-Correlation-ID
     * обычно ставит балансировщик, и переиспользовать их лучше, чем плодить второй id.
     *
     * @var list<string>
     */
    private const HEADERS = ['X-Trace-ID', 'X-Trace-Id', 'X-Request-ID', 'X-Request-Id', 'X-Correlation-ID', 'X-Correlation-Id'];

    private ?string $value = null;

    public function __construct(
        private readonly ?Request $request = null,
    ) {}

    public function value(): string
    {
        return $this->value ??= $this->resolve();
    }

    private function resolve(): string
    {
        if ($this->request instanceof Request) {
            foreach (self::HEADERS as $header) {
                $value = $this->request->headers->get($header);

                if (is_string($value) && trim($value) !== '') {
                    return $this->sanitize($value);
                }
            }
        }

        return (string) Str::uuid();
    }

    /**
     * Значение приходит извне и уезжает в лог и в тело ответа, поэтому режем длину
     * и оставляем только безопасный алфавит: иначе внешний заголовок мог бы
     * протащить в лог перевод строки и подделать запись.
     */
    private function sanitize(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9\-_.]/', '', $value) ?? '';
        $clean = substr($clean, 0, 128);

        return $clean !== '' ? $clean : (string) Str::uuid();
    }
}
