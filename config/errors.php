<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Validation\ValidationException;
use Polymorph\Platform\Domain\Auth\Application\Exceptions\TokenManagementDeniedException;
use Polymorph\Platform\Domain\Auth\Core\Exceptions\JwtConfigurationException;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

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
        ErrorCode::INVALID_JSON_VALUE->value => [
            'uri' => 'https://polymorph.dev/problems/invalid-json-value',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'The provided JSON value is invalid.',
        ],
        ErrorCode::INVALID_PLUGIN_MANIFEST->value => [
            'uri' => 'https://polymorph.dev/problems/invalid-plugin-manifest',
            'title' => 'Invalid plugin manifest',
            'status' => 422,
            'detail' => 'Plugin manifest is invalid.',
        ],
        ErrorCode::PLUGIN_ALREADY_DISABLED->value => [
            'uri' => 'https://polymorph.dev/problems/plugin-already-disabled',
            'title' => 'Plugin already disabled',
            'status' => 409,
            'detail' => 'Plugin is already disabled.',
        ],
        ErrorCode::PLUGIN_ALREADY_ENABLED->value => [
            'uri' => 'https://polymorph.dev/problems/plugin-already-enabled',
            'title' => 'Plugin already enabled',
            'status' => 409,
            'detail' => 'Plugin is already enabled.',
        ],
        ErrorCode::PLUGIN_NOT_FOUND->value => [
            'uri' => 'https://polymorph.dev/problems/plugin-not-found',
            'title' => 'Plugin not found',
            'status' => 404,
            'detail' => 'Plugin was not found.',
        ],
        ErrorCode::PLUGIN_INCOMPATIBLE->value => [
            'uri' => 'https://polymorph.dev/problems/plugin-incompatible',
            'title' => 'Plugin incompatible with core',
            'status' => 409,
            'detail' => 'Plugin declares a core version range that does not match the current core version.',
        ],
        ErrorCode::PLUGIN_DEPENDENCY_FAILED->value => [
            'uri' => 'https://polymorph.dev/problems/plugin-dependency-failed',
            'title' => 'Plugin dependency check failed',
            'status' => 409,
            'detail' => 'Plugin dependency requirements are not satisfied.',
        ],
        ErrorCode::ROUTES_RELOAD_FAILED->value => [
            'uri' => 'https://polymorph.dev/problems/routes-reload-failed',
            'title' => 'Failed to reload plugin routes',
            'status' => 500,
            'detail' => 'Failed to reload plugin routes.',
        ],
        ErrorCode::MEDIA_DOWNLOAD_ERROR->value => [
            'uri' => 'https://polymorph.dev/problems/media-download-error',
            'title' => 'Failed to download media',
            'status' => 500,
            'detail' => 'Failed to generate download URL.',
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

    'mappings' => [
        ValidationException::class => [
            'builder' => static function (ValidationException $exception, ErrorFactory $factory): ErrorPayload {
                $errors = $exception->errors();
                $detail = null;

                foreach ($errors as $messages) {
                    foreach ($messages as $message) {
                        if (is_string($message) && trim($message) !== '') {
                            $detail = $message;
                            break 2;
                        }
                    }
                }

                $detail ??= 'Validation failed.';

                return $factory->for(ErrorCode::VALIDATION_ERROR)
                    ->detail($detail)
                    ->meta(['errors' => $errors])
                    ->build();
            },
        ],
        AuthenticationException::class => [
            'builder' => static function (AuthenticationException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Authentication is required to access this resource.';
                }

                return $factory->for(ErrorCode::UNAUTHORIZED)
                    ->detail($detail)
                    ->build();
            },
        ],
        AuthorizationException::class => [
            'builder' => static function (AuthorizationException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Admin privileges are required.';
                }

                return $factory->for(ErrorCode::FORBIDDEN)
                    ->detail($detail)
                    ->build();
            },
        ],
        TokenManagementDeniedException::class => [
            'builder' => static function (TokenManagementDeniedException $exception, ErrorFactory $factory): ErrorPayload {
                return $factory->for(ErrorCode::FORBIDDEN)
                    ->detail($exception->getMessage())
                    ->build();
            },
        ],
        AccessDeniedHttpException::class => [
            'builder' => static function (AccessDeniedHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Admin privileges are required.';
                }

                return $factory->for(ErrorCode::FORBIDDEN)
                    ->detail($detail)
                    ->build();
            },
        ],
        NotFoundHttpException::class => [
            'builder' => static function (NotFoundHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'The requested resource was not found.';
                }

                return $factory->for(ErrorCode::NOT_FOUND)
                    ->detail($detail)
                    ->build();
            },
        ],
        QueryException::class => [
            'builder' => static function (QueryException $exception, ErrorFactory $factory): ErrorPayload {
                $previous = $exception->getPrevious();

                if ($previous instanceof HttpErrorException) {
                    return $previous->payload();
                }

                return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build();
            },
            'report' => [
                'level' => 'error',
                'message' => 'Database error during API request',
                'context' => static fn (QueryException $exception, ErrorPayload $payload): array => [
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                ],
            ],
        ],
        ServiceUnavailableHttpException::class => [
            'builder' => static function (ServiceUnavailableHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Service is temporarily unavailable.';
                }

                $builder = $factory->for(ErrorCode::SERVICE_UNAVAILABLE)
                    ->detail($detail);

                $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
                if ($retryAfter !== null) {
                    $builder = $builder->addMeta('retry_after', $retryAfter);
                }

                return $builder->build();
            },
            'report' => [
                'level' => 'error',
                'message' => 'Service unavailable during API request',
            ],
        ],
        PostTooLargeException::class => [
            'builder' => static function (PostTooLargeException $exception, ErrorFactory $factory): ErrorPayload {
                $postMaxSize = ini_get('post_max_size') ?: 'unknown';
                $uploadMaxFilesize = ini_get('upload_max_filesize') ?: 'unknown';
                $maxSize = $postMaxSize !== 'unknown' ? $postMaxSize : $uploadMaxFilesize;

                return $factory->for(ErrorCode::VALIDATION_ERROR)
                    ->detail('The uploaded file exceeds the maximum allowed size.')
                    ->meta([
                        'max_size' => $maxSize,
                        'post_max_size' => $postMaxSize,
                        'upload_max_filesize' => $uploadMaxFilesize,
                        'error_type' => 'post_too_large',
                    ])
                    ->build();
            },
        ],

        // Auth domain exceptions with special handling
        JwtConfigurationException::class => [
            'builder' => static function (JwtConfigurationException $exception, ErrorFactory $factory): ErrorPayload {
                // Use ErrorConvertible interface
                return $exception->toError($factory);
            },
            'report' => [
                'level' => 'critical',
                'message' => 'JWT configuration error detected',
                'context' => static fn (JwtConfigurationException $exception): array => [
                    'message' => $exception->getMessage(),
                    'suggestion' => 'Check .env file for JWT_SECRET configuration',
                ],
            ],
        ],
        // Safety net: unwrapped standard PHP exceptions
        // These should be replaced with domain-specific exceptions over time
        InvalidArgumentException::class => [
            'builder' => static function (InvalidArgumentException $exception, ErrorFactory $factory): ErrorPayload {
                return $factory->for(ErrorCode::BAD_REQUEST)
                    ->detail($exception->getMessage())
                    ->meta(['exception_type' => 'InvalidArgumentException'])
                    ->build();
            },
            'report' => [
                'level' => 'warning',
                'message' => 'Unwrapped InvalidArgumentException in API request',
                'context' => static fn (InvalidArgumentException $exception): array => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            ],
        ],

        RuntimeException::class => [
            'builder' => static function (RuntimeException $exception, ErrorFactory $factory): ErrorPayload {
                return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)
                    ->build();
            },
            'report' => [
                'level' => 'error',
                'message' => 'Unwrapped RuntimeException in API request',
                'context' => static fn (RuntimeException $exception): array => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            ],
        ],

        UnexpectedValueException::class => [
            'builder' => static function (UnexpectedValueException $exception, ErrorFactory $factory): ErrorPayload {
                return $factory->for(ErrorCode::BAD_REQUEST)
                    ->detail('Invalid value provided')
                    ->meta(['exception_type' => 'UnexpectedValueException'])
                    ->build();
            },
            'report' => [
                'level' => 'warning',
                'message' => 'Unwrapped UnexpectedValueException in API request',
                'context' => static fn (UnexpectedValueException $exception): array => [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
            ],
        ],
    ],

    'fallback' => [
        'builder' => static function (Throwable $throwable, ErrorFactory $factory): ErrorPayload {
            return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build();
        },
        'report' => [
            'level' => 'error',
            'message' => 'Unhandled exception in API request',
        ],
    ],
];
