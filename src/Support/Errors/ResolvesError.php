<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Illuminate\Http\Request;
use Throwable;

/**
 * Шов, через который тип исключения получает свой перевод в ErrorPayload.
 *
 * Существует потому, что конвертеры живут в разных слоях. `FrameworkErrorResolver`
 * знает фреймворк и SPL и может лежать в Support; `ReplyRenderer` знает ошибки SDK
 * и живёт в Domain\Extensions. Захардкодить второй в Support нельзя — это зависимость
 * вверх. Раньше такого шва не было, поэтому «лишние» конвертеры оседали ветвлениями
 * в HTTP bootstrap, и тот де-факто стал реестром, написанным руками в control flow.
 *
 * Порядок резолверов важен и задан в одном месте — там, где собирается ErrorKernel
 * (сервис-провайдер приложения). Назвать его здесь ссылкой нельзя: Support — нижний
 * слой, и вверх он не смотрит даже комментарием.
 *
 * Интерфейс лежит в Support, а не в SharedKernel, намеренно: он принимает
 * Illuminate\Request, а нейтральность SharedKernel остаётся целью.
 */
interface ResolvesError
{
    /**
     * null — «это не мой тип, спрашивай следующего».
     *
     * Запрос передаётся потому, что часть решений опирается на его атрибуты
     * (причина отказа аутентификации). Резолвер обязан работать и без него.
     */
    public function resolve(Throwable $exception, ?Request $request = null): ?ErrorResolution;
}
