<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/**
 * Конфиг php-scoper для изоляции vendor плагина. Префикс задаётся per-plugin
 * через env POLYMORPH_SCOPER_PREFIX (см. PluginsBuildCommand). Scoper запускается с
 * --working-dir = корень исходников плагина, поэтому finders видят `vendor/` и
 * `be/` относительно него.
 */
$prefix = getenv('POLYMORPH_SCOPER_PREFIX');
if (! is_string($prefix) || $prefix === '') {
    $prefix = 'PolymorphScoped';
}

return [
    'prefix' => $prefix,

    // Сканируем ВСЕ файлы (не только *.php): php-scoper переписывает PHP, а
    // прочие ассеты (сертификаты, шаблоны, json) копирует как есть — иначе они
    // выпали бы из scoped-дерева.
    'finders' => [
        // vendor/polymorph/* — host-provided SDK-пакет (sdk),
        // подключённый path-репозиторием как junction/symlink. Его НЕ скоупим и НЕ бандлим
        // (предоставляет ядро); к тому же scoper-finder спотыкается о junction-каталог.
        Finder::create()->files()->ignoreVCS(true)->exclude(['polymorph'])->in('vendor'),
        Finder::create()->files()->ignoreVCS(true)->in('be'),
    ],

    // НЕ префиксуем namespace, которые ПРЕДОСТАВЛЯЕТ ХОСТ (ядро) в рантайме —
    // их нет в vendor плагина, и/или они общие контракты через границу плагин↔ядро.
    // Префиксование сломало бы резолв (PolymorphScoped\...\App\... не существует) или DI
    // (несовпадение типов на границе).
    //  - Plugins\*    — собственный namespace плагина (резолв из be/ по psr4Path;
    //                   FQCN провайдера в манифесте обязан совпасть).
    //  - Polymorph\Sdk\* — контракты Extension SDK V2 (предоставляет ядро).
    //  - Polymorph\Platform\* — код ядра приложения (плагин слушает его события и т.п.).
    //  - Illuminate\* — фреймворк Laravel ядра.
    //  - Psr\*        — общие PSR-контракты (log/container/http-message), должны
    //                   совпадать по обе стороны границы.
    //  - Symfony\Component\HttpFoundation\* — HTTP-слой Laravel ядра (Request/Response
    //                   живут на нём). Контроллер плагина принимает Request и возвращает
    //                   Response ХОСТА; префиксование ломает type-hint (Illuminate\…\
    //                   JsonResponse расширяет НЕпрефиксованный Symfony Response → TypeError).
    //                   Плагины свой http-foundation не бандлят — это всегда тип ядра.
    // Прочие собственные сторонние зависимости плагина (GuzzleHttp, League, Mcp,
    // остальной Symfony, Nyholm, …) ПРЕФИКСУЮТСЯ — ради этого и нужна изоляция.
    'exclude-namespaces' => [
        '~^Plugins(\\\\|$)~',
        '~^Polymorph\\\\Sdk(\\\\|$)~',
        '~^Polymorph\\\\Platform(\\\\|$)~',
        '~^Illuminate(\\\\|$)~',
        '~^Psr(\\\\|$)~',
        '~^Symfony\\\\Component\\\\HttpFoundation(\\\\|$)~',
    ],

    // Глобальные символы (нативные классы/функции/константы, symfony-полифилы)
    // оставляем в глобальном namespace — иначе полифилы и нативные вызовы сломаются.
    'expose-global-constants' => true,
    'expose-global-classes' => true,
    'expose-global-functions' => true,
];
