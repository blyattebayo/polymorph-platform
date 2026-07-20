<?php

declare(strict_types=1);

namespace Plugins\__PLUGIN_CLASS__\Http\Controllers;

use Polymorph\Sdk\Http\Reply;

final class __PLUGIN_CLASS__HelloController
{
    public function __invoke(): Reply
    {
        return Reply::ok([
            'plugin' => '__PLUGIN_ID__',
            'message' => 'Hello from __PLUGIN_NAME__',
        ]);
    }
}
