#!/usr/bin/env bash
# Make the LIVE site an exact copy of the approved TEST site (database + files),
# keeping the live site's own configuration.php. Back up the live site first!
#
#   sudo bash go-live.sh <live-folder> <test-folder> [test-db-name]
set -euo pipefail

LIVE=${1:?live folder}; STAGE=${2:?test folder}; TDB=${3:-pamperins_test}
MYSQL_ADMIN=${MYSQL_ADMIN:-mysql}; MYSQLDUMP_ADMIN=${MYSQLDUMP_ADMIN:-mysqldump}
# Backups are made by the normal (non-root) user in their ~/backups, even when this runs under sudo
BACKUP_DIR=${BACKUP_DIR:-$(getent passwd "${SUDO_USER:-$(id -un)}" | cut -d: -f6)/backups}
LIVE=${LIVE%/}; STAGE=${STAGE%/}

die() { echo "ERROR: $*" >&2; exit 1; }
cfg() { sed -n "s/^[[:space:]]*public \$$1 = '\(.*\)';.*/\1/p" "$2" | head -1; }

[ -f "$LIVE/configuration.php" ] && [ -f "$STAGE/configuration.php" ] || die "configuration.php missing"
LDB=$(cfg db "$LIVE/configuration.php")
[ -n "$LDB" ] && [ "$LDB" != "$TDB" ] || die "live and test databases look the same"
[ "$(cfg db "$STAGE/configuration.php")" = "$TDB" ] || die "test folder does not use database $TDB"
LP=$(cfg dbprefix "$LIVE/configuration.php"); TP=$(cfg dbprefix "$STAGE/configuration.php")
[ "$LP" = "$TP" ] || die "table prefixes differ ($LP vs $TP)"

ls "$BACKUP_DIR"/pamperins-db-*.sql.gz >/dev/null 2>&1 || die "no backup in $BACKUP_DIR — run the backup step first"
echo "Newest live backup: $(ls -t "$BACKUP_DIR"/pamperins-db-*.sql.gz | head -1)"
read -r -p "Replace the LIVE site ($LIVE, database $LDB) with the test copy? Type YES: " ok
[ "$ok" = "YES" ] || die "cancelled"

TMP=$(mktemp)
echo "== Copying database $TDB -> $LDB"
$MYSQLDUMP_ADMIN --single-transaction --no-tablespaces "$TDB" > "$TMP"
$MYSQL_ADMIN "$LDB" < "$TMP"; rm -f "$TMP"

echo "== Copying files (keeping the live configuration.php)"
rsync -a --exclude=configuration.php --exclude='cache/*' --exclude='administrator/cache/*' --exclude='tmp/*' --exclude='administrator/logs/*' "$STAGE/" "$LIVE/"
chown -R "$(stat -c '%U:%G' "$LIVE/configuration.php")" "$LIVE"
rm -rf "$LIVE"/cache/* "$LIVE"/administrator/cache/* 2>/dev/null || true
sed -i "s|^\([[:space:]]*public \$robots = \).*;|\1'';|" "$LIVE/configuration.php"   # let search engines in again
echo "Done. Check https://pamperins.com now (DEPLOY.md step 7)."
