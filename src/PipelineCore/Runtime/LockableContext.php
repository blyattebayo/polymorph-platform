<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

use Polymorph\Platform\PipelineCore\Locking\LockKey;

interface LockableContext extends PipelineContext
{
    public function getLockKey(): LockKey;
}
