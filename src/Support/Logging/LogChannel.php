<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

final class LogChannel
{
    public const APP = 'app';

    public const API = 'api';

    public const AUTH = 'auth';

    public const MEDIA = 'media';

    public const PIPELINE = 'pipeline';

    public const PLUGINS = 'plugins';

    public const ROUTING = 'routing';

    public const SCHEMA = 'schema';

    private function __construct() {}
}
