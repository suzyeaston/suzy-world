#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$DIST" "$STAGE/suzy-world"

cp "$ROOT/suzy-world.php" "$STAGE/suzy-world/"
cp -R "$ROOT/includes" "$STAGE/suzy-world/includes"
cp -R "$ROOT/public" "$STAGE/suzy-world/public"
cp "$ROOT/README.md" "$STAGE/suzy-world/"
cp "$ROOT/LICENSE" "$STAGE/suzy-world/"

rm -f "$DIST/suzy-world.zip"
(
  cd "$STAGE"
  zip -qr "$DIST/suzy-world.zip" suzy-world
)

echo "Built: $DIST/suzy-world.zip"
