<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http\Controllers;

use Polymorph\Platform\Domain\Extensions\Services\ExtensionDiscoveryService;
use Polymorph\Platform\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExtensionAssetController extends Controller
{
    /** @var array<string, string> */
    private const MIME = [
        'js' => 'text/javascript',
        'mjs' => 'text/javascript',
        'css' => 'text/css',
        'map' => 'application/json',
        'json' => 'application/json',
        'html' => 'text/html',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'wasm' => 'application/wasm',
        'txt' => 'text/plain',
    ];

    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
    ) {}

    public function show(string $plugin, string $path): BinaryFileResponse
    {
        $extension = $this->discovery->find($plugin);
        if ($extension === null || ! $extension->hasFrontend) {
            abort(404);
        }

        $base = realpath(dirname($extension->manifestPath).DIRECTORY_SEPARATOR.'fe'.DIRECTORY_SEPARATOR.'dist');
        if ($base === false) {
            abort(404);
        }

        $relative = str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
        $target = realpath($base.DIRECTORY_SEPARATOR.$relative);

        if ($target === false
            || ! is_file($target)
            || ! str_starts_with($target, $base.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = self::MIME[$extension] ?? 'application/octet-stream';

        $cacheControl = app()->environment('production')
            ? 'public, max-age=31536000, immutable'
            : 'no-cache, must-revalidate';

        $response = response()->file($target, [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheControl,
        ]);

        $response->headers->set('Content-Type', $mime);

        return $response;
    }
}
