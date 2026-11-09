#!/usr/bin/env bash
# Builds the installable Joomla packages:
#   pamperins-template.zip         the site template      (template/)
#   pamperins-support-tickets.zip  the support tickets    (extensions/com_ptickets/)
# Each zip has its manifest XML at the root (no nested folder).
set -euo pipefail
cd "$(dirname "$0")"

command -v zip >/dev/null || { echo "zip is not installed (sudo apt install zip)"; exit 1; }

package() {
  local src="$1" manifest="$2" out="$3"
  [ -f "$src/$manifest" ] || { echo "Missing $src/$manifest"; exit 1; }
  if command -v php >/dev/null; then
    php -r 'simplexml_load_file($argv[1]) or exit(1);' "$src/$manifest" || { echo "$manifest is not valid XML"; exit 1; }
    find "$src" -name '*.xml' -print0 | xargs -0 -I{} php -r 'simplexml_load_file($argv[1]) or exit(1);' {} || { echo "Invalid XML in $src"; exit 1; }
    find "$src" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
  fi
  rm -f "$out"
  ( cd "$src" && zip -qr -X "../$(basename "$out")" . -x '.*' -x '*/.*' -x '*~' )
  [ "$(dirname "$src")" = "." ] || mv "$(dirname "$src")/$(basename "$out")" "$out"
  unzip -l "$out" | grep -q " $manifest\$" && echo "OK: $out ($manifest at zip root, $(unzip -l "$out" | tail -1 | awk '{print $2}') files)"
}

package template templateDetails.xml pamperins-template.zip
package extensions/com_ptickets ptickets.xml pamperins-support-tickets.zip
