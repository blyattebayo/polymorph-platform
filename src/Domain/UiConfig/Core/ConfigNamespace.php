<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core;

use Polymorph\Platform\Domain\UiConfig\Infrastructure\UiConfigStore;

/**
 * Полный словарь видов UI-конфига и всё, чем они друг от друга отличаются.
 *
 * Раньше на каждый вид приходился свой домен с контроллером и сервисом, хотя
 * различий между видами не осталось вовсе: адресация внутри вида — дело клиента,
 * прав на запись нет ни у одного, механика записи общая, см. {@see UiConfigStore}.
 * Словарь остался закрытым только чтобы опечатка в виде не заводила новый вид.
 *
 * Значение case — это `ui_configs.namespace` и сегмент URL чтения одновременно.
 */
enum ConfigNamespace: string
{
    case MENU = 'menu';
    case ENTRY_VIEW = 'entry_view';
    case TABLE = 'table';

}
