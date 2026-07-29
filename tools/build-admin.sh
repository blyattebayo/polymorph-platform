#!/usr/bin/env bash
#
# Rebuild the admin SPA and embed its artifact into the platform package.
#
# The compiled bundle ships INSIDE blyattebayo/polymorph (resources/admin/dist) and is
# served by Admin\Http\AdminAssetController, so a thin host gets a working admin panel
# straight after `composer require` — no host-side Node build, no publish step.
#
# Run this before tagging/publishing a platform release, and COMMIT the result: версия
# пакета = git-тег, поэтому в тег обязан попасть уже свежий бандл.
#
# Поля "version" в composer.json нет намеренно (оно перекрывает версию тега), и
# sdk-v2/tests/smoke_version.php это проверяет — прежняя инструкция «бампни version
# в composer.json» относилась к локальному Satis и сейчас сломала бы гейт.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLATFORM_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"      # <repo>/be/platform
FE_DIR="$(cd "$PLATFORM_DIR/../../fe" && pwd)"    # <repo>/fe
DEST_ROOT="$PLATFORM_DIR/resources/admin"

echo "==> Building admin SPA in $FE_DIR"
rm -rf "$FE_DIR/dist"
( cd "$FE_DIR" && npm ci && npm run build )

echo "==> Embedding dist into $DEST_ROOT/dist"
rm -rf "$DEST_ROOT"
mkdir -p "$DEST_ROOT"
cp -r "$FE_DIR/dist" "$DEST_ROOT/"

echo "==> Done."
echo "    Next: закоммить dist — версия пакета берётся из git-тега, и в тег должен"
echo "    попасть уже свежий бандл. Поля 'version' в composer.json нет намеренно."
