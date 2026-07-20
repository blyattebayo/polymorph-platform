# polymorph/platform

The Polymorph platform core, packaged as a Composer library (ADR 0006). A thin host
application consumes it from the package registry and boots a full headless CMS —
domain services, HTTP API, CLI, and the **admin panel** — with no code of its own
beyond `bootstrap/app.php`.

## Boot

Auto-discovered via `extra.laravel.providers`:

- `PlatformServiceProvider` registers the 20 domain providers, merges the platform
  config, and loads migrations, translations and Blade views.
- `Bootstrap\HostBootstrap` supplies the middleware / exceptions / console wiring that a
  service provider cannot reach in Laravel 12; the thin host's `bootstrap/app.php`
  delegates to it.

## Admin panel

The admin SPA is shipped **prebuilt inside this package** (`resources/admin/dist`, a Vite
build) and served from it — so a host gets the admin panel immediately after
`composer require`, with no host-side frontend build and no publish step:

- `AdminShellController` renders the SPA shell for the admin mount path and every route
  beneath it (deep links / reloads work).
- `AdminAssetController` streams the hashed bundle assets with an immutable cache.

The mount path is host-configurable via `config('admin.path')` (env `ADMIN_PATH`,
default `admin`) with no rebuild: the bundle is built with a relative base and the shell
injects the mount path at runtime for the client router. Example: `ADMIN_PATH=manage`
serves the panel at `/manage`.

The public site stays at `/` (Blade views ship in `resources/views`; a host can override
them by publishing `--tag=polymorph-views`).

## Rebuilding the admin artifact (release step)

The compiled bundle is committed to this package. To refresh it:

```bash
be/platform/tools/build-admin.sh
```

This rebuilds `<repo>/fe` and copies the result into `resources/admin/dist`. Then **bump
`version` in `composer.json`** before publishing — the local Satis registry caches
package dist by version, so a release without a version bump ships the stale bundle.
