<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Pagination\V2;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class PaginatedJsonResponse
{
    public static function from(PageResult $result): JsonResponse
    {
        return AdminResponse::json($result->toArray());
    }
}
