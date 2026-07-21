<?php

declare(strict_types=1);

namespace Polymorph\Platform\Admin\Http;

use Illuminate\Http\Response;
use Polymorph\Platform\Admin\AdminBundle;

/**
 * Serves the admin SPA shell (index document) for the admin mount path and every
 * client-side route beneath it, so deep links and reloads work. The bundle is
 * built with a relative base; this shell only needs to point the entry tags at the
 * host-configured mount path and inject that path for the client router.
 */
final class AdminShellController
{
    public function __invoke(): Response
    {
        $manifest = AdminBundle::manifest();
        $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;

        if (! is_array($entry) || ! isset($entry['file'])) {
            return new Response(
                "Admin UI is unavailable: this blyattebayo/polymorph build does not include the admin bundle (resources/admin/dist).\n",
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $base = AdminBundle::basePath();
        $jsUrl = e($base.'/'.ltrim((string) $entry['file'], '/'));

        $styleTags = '';
        foreach (array_values((array) ($entry['css'] ?? [])) as $css) {
            $href = e($base.'/'.ltrim((string) $css, '/'));
            $styleTags .= "    <link rel=\"stylesheet\" crossorigin href=\"{$href}\">\n";
        }

        $baseJson = json_encode($base, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $html = <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Polymorph</title>
    <script>window.__POLYMORPH_ADMIN_BASE__ = {$baseJson};</script>
{$styleTags}    <script type="module" crossorigin src="{$jsUrl}"></script>
  </head>
  <body>
    <div id="app"></div>
  </body>
</html>

HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // The shell references content-hashed assets; never cache the shell
            // itself so a redeployed bundle is picked up on the next request.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
