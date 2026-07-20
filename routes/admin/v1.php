<?php

declare(strict_types=1);

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

$children = [];
$files = glob(__DIR__.'/v1/*.php') ?: [];
sort($files);

foreach ($files as $file) {
    $chunk = require $file;

    if (! is_array($chunk)) {
        continue;
    }

    foreach ($chunk as $node) {
        if (is_array($node)) {
            $children[] = $node;
        }
    }
}

return [
    [
        'kind' => RouteNodeKind::GROUP,
        'sort_order' => -998,
        'prefix' => 'api/v1/admin',
        'middleware' => ['api', 'auth:api'],
        'children' => $children,
    ],
];
