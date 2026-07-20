<?php

declare(strict_types=1);

namespace Polymorph\Platform\Admin;

/**
 * Locator for the prebuilt admin SPA embedded in the package (resources/admin/dist,
 * a Vite build). Shared by the shell controller (needs the entry manifest + mount
 * path) and the asset controller (needs a traversal-safe path into the bundle).
 */
final class AdminBundle
{
    /** Absolute path to the embedded Vite dist directory. */
    public static function distPath(): string
    {
        // __DIR__ = <package>/src/Admin -> up 2 = <package>.
        return dirname(__DIR__, 2).'/resources/admin/dist';
    }

    /** Configured mount segment without surrounding slashes, e.g. 'admin'. */
    public static function routePrefix(): string
    {
        $path = trim((string) config('admin.path', 'admin'), '/');

        return $path === '' ? 'admin' : $path;
    }

    /** Absolute mount path for the client router and asset URLs, e.g. '/admin'. */
    public static function basePath(): string
    {
        return '/'.self::routePrefix();
    }

    /**
     * Decoded Vite manifest, or null when the package was built without the admin
     * artifact (resources/admin/dist absent).
     *
     * @return array<string, mixed>|null
     */
    public static function manifest(): ?array
    {
        $file = self::distPath().'/.vite/manifest.json';

        if (! is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Resolve an asset request (relative to dist/assets) to an absolute file path
     * inside the bundle, or null if it is missing or escapes the assets directory.
     */
    public static function assetPath(string $relative): ?string
    {
        $assetsRoot = realpath(self::distPath().'/assets');

        if ($assetsRoot === false) {
            return null;
        }

        $candidate = realpath($assetsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($candidate === false || ! str_starts_with($candidate, $assetsRoot.DIRECTORY_SEPARATOR)) {
            return null; // missing or path-traversal attempt
        }

        return is_file($candidate) ? $candidate : null;
    }
}
