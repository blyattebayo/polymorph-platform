#!/usr/bin/env bash
#
# Rebuild the admin SPA and embed its artifact into the platform package.
#
# The compiled bundle ships INSIDE polymorph/platform (resources/admin/dist) and is
# served by Admin\Http\AdminAssetController, so a thin host gets a working admin panel
# straight after `composer require` — no host-side Node build, no publish step.
#
# Run this before tagging/publishing a platform release. Then BUMP the platform version
# (composer.json) — the local Satis registry caches package dist by version, so a release
# without a version bump ships the stale bundle.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLATFORM_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"      # <repo>/be/platform
FE_DIR="$(cd "$PLATFORM_DIR/../../fe" && pwd)"    # <repo>/fe
DEST_ROOT="$PLATFORM_DIR/resources/admin"

echo "==> Building admin SPA in $FE_DIR"
( cd "$FE_DIR" && npm ci && npm run build )

echo "==> Embedding dist into $DEST_ROOT/dist"
rm -rf "$DEST_ROOT"
mkdir -p "$DEST_ROOT"
cp -r "$FE_DIR/dist" "$DEST_ROOT/"

echo "==> Done."
echo "    Next: bump 'version' in be/platform/composer.json before publishing to the registry."
