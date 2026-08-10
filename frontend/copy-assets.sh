#!/usr/bin/env bash
#
# Copies the Foogra template's static assets into the Vite public folder, so
# the React components can reuse the original stylesheets, icon fonts, demo
# imagery and hero video verbatim.
#
set -euo pipefail

SRC=$(ls -d /mnt/c/Users/Dell/Downloads/foogra-*/foogra_v.3.6/html 2>/dev/null | head -1)
DEST="$(cd "$(dirname "$0")" && pwd)/public"

if [ -z "$SRC" ]; then
  echo "Could not find the Foogra template under ~/Downloads." >&2
  exit 1
fi

echo "source: $SRC"
echo "dest:   $DEST"

mkdir -p "$DEST/css"

cp -r "$SRC/img" "$DEST/"
cp -r "$SRC/video" "$DEST/"

# Only the stylesheets the three ported pages actually reference.
for f in bootstrap.min.css style.css home.css listing.css detail-page.css review.css custom.css icons.css; do
  if [ -f "$SRC/css/$f" ]; then
    cp "$SRC/css/$f" "$DEST/css/"
  else
    echo "  missing: css/$f" >&2
  fi
done

# Icon fonts and background assets referenced by url() inside those stylesheets.
for d in icon_fonts bs-icon-font images overlays; do
  [ -d "$SRC/css/$d" ] && cp -r "$SRC/css/$d" "$DEST/css/"
done

echo
du -sh "$DEST"/* | sort -h
