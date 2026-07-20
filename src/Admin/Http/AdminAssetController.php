<?php

declare(strict_types=1);

namespace Polymorph\Platform\Admin\Http;

use Illuminate\Http\Response;
use Polymorph\Platform\Admin\AdminBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Streams the admin SPA's static assets (hashed JS/CSS/fonts) straight from the
 * embedded bundle, so the admin panel works right after `composer require` with no
 * publish step. Filenames are content-hashed and therefore immutable.
 */
final class AdminAssetController
{
    public function show(string $asset): SymfonyResponse
    {
        $path = AdminBundle::assetPath($asset);

        if ($path === null) {
            return new Response('Not Found', Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', self::mimeFor($path));
        $response->setPublic();
        // Hashed filenames never change content -> cache for a year, immutable.
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    private static function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'js', 'mjs' => 'text/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'json', 'map' => 'application/json',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
