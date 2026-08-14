<?php

declare(strict_types=1);

use Polymorph\Platform\Support\Errors\ErrorCode;

/**
 * Каталог типов ошибок (RFC 7807): код -> uri, заголовок, HTTP-статус, дефолтная деталь.
 *
 * Только данные. Решения «какое исключение во что превращается» и «как его писать
 * в лог» живут в коде: FrameworkErrorResolver и ErrorReportPolicy. Раньше здесь же
 * лежали 13 замыканий-билдеров — их нельзя было покрыть тестом по отдельности,
 * они были невидимы статическому анализатору, а порядок ключей массива молча
 * решал приоритет и затенял три из них.
 */
return [
    'types' => [
        ErrorCode::BAD_REQUEST->value => [
            'uri' => 'https://polymorph.dev/problems/bad-request',
            'title' => 'Bad Request',
            'status' => 400,
            'detail' => 'The request could not be understood or was missing required parameters.',
        ],
        ErrorCode::UNAUTHORIZED->value => [
            'uri' => 'https://polymorph.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ],
        ErrorCode::FORBIDDEN->value => [
            'uri' => 'https://polymorph.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ],
        ErrorCode::NOT_FOUND->value => [
            'uri' => 'https://polymorph.dev/problems/not-found',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'The requested resource was not found.',
        ],
        ErrorCode::VALIDATION_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/validation-error',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'Validation failed.',
        ],
        ErrorCode::CONFLICT->value => [
            'uri' => 'https://polymorph.dev/problems/conflict',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'The request conflicts with the current state of the resource.',
        ],
        ErrorCode::PRECONDITION_REQUIRED->value => [
            'uri' => 'https://polymorph.dev/problems/precondition-required',
            'title' => 'Precondition Required',
            'status' => 428,
            'detail' => 'A revision precondition is required for this request.',
        ],
        ErrorCode::TOO_MANY_REQUESTS->value => [
            'uri' => 'https://polymorph.dev/problems/too-many-requests',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Too many requests. Please retry later.',
        ],
        ErrorCode::SERVICE_UNAVAILABLE->value => [
            'uri' => 'https://polymorph.dev/problems/service-unavailable',
            'title' => 'Service Unavailable',
            'status' => 503,
            'detail' => 'Service is temporarily unavailable.',
        ],
        ErrorCode::INTERNAL_SERVER_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'An unexpected error occurred.',
        ],
        ErrorCode::INVALID_PLUGIN_MANIFEST->value => [
            'uri' => 'https://polymorph.dev/problems/invalid-plugin-manifest',
            'title' => 'Invalid plugin manifest',
            'status' => 422,
            'detail' => 'Plugin manifest is invalid.',
        ],
        ErrorCode::MEDIA_DOWNLOAD_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/media-download-error',
            'title' => 'Failed to download media',
            'status' => 500,
            'detail' => 'Failed to generate download URL.',
        ],
        ErrorCode::MEDIA_STORAGE_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/media-storage-error',
            'title' => 'Media storage error',
            'status' => 500,
            'detail' => 'Failed to access media storage.',
        ],
        ErrorCode::MEDIA_VARIANT_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/media-variant-error',
            'title' => 'Failed to generate media variant',
            'status' => 500,
            'detail' => 'Failed to generate media variant.',
        ],
        ErrorCode::CSRF_TOKEN_MISMATCH->value => [
            'uri' => 'https://polymorph.dev/problems/csrf-token-mismatch',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ],
    ],
];
