#!/usr/bin/env bash
# Packages the Pamperins Joomla template into pamperins-template.zip
# with templateDetails.xml at the zip root (no nested folder).
set -euo pipefail

cd "$(dirname "$0")"
SRC="template"
OUT="pamperins-template.zip"

command -v zip >/dev/null || { echo "zip is not installed (sudo apt install zip)"; exit 1; }

# Basic sanity checks before packaging
[ -f "$SRC/templateDetails.xml" ] || { echo "Missing $SRC/templateDetails.xml"; exit 1; }
if command -v php >/dev/null; then
  php -r 'simplexml_load_file($argv[1]) or exit(1);' "$SRC/templateDetails.xml" || { echo "templateDetails.xml is not valid XML"; exit 1; }
  find "$SRC" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi

rm -f "$OUT"
( cd "$SRC" && zip -qr -X "../$OUT" . -x '.*' -x '*/.*' -x '*~' )

echo "Built $OUT:"
unzip -l "$OUT" | awk 'NR>3 && $4 != "" {print "  " $4}'
unzip -l "$OUT" | grep -q ' templateDetails.xml$' && echo "OK: templateDetails.xml is at the zip root."
