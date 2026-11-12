#!/usr/bin/env bash
# Back up a Joomla site (database + files) into the calling user's ~/backups.
#   sudo bash backup.sh /var/www/new.pamperins.com
# Restore (same date for both files):
#   sudo bash backup.sh --restore /var/www/new.pamperins.com 2026-11-12-1430
set -euo pipefail

MODE=backup; [ "${1:-}" = --restore ] && { MODE=restore; shift; }
SITE=${1:?Joomla folder, e.g. /var/www/new.pamperins.com}; SITE=${SITE%/}
OWNER=${SUDO_USER:-$(id -un)}
DEST=$(getent passwd "$OWNER" | cut -d: -f6)/backups
C="$SITE/configuration.php"
die() { echo "ERROR: $*" >&2; exit 1; }
cfg() { sed -n "s/^[[:space:]]*public \$$1 = '\(.*\)';.*/\1/p" "$C" | head -1; }

[ "$(id -u)" = 0 ] || die "run with sudo"
[ -f "$C" ] || die "$C not found"
DB=$(cfg db); HOST=$(cfg host); USER=$(cfg user); PASS=$(cfg password)
NAME=$(basename "$SITE")

if [ "$MODE" = backup ]; then
  D=$(date +%F-%H%M); mkdir -p "$DEST"
  MYSQL_PWD="$PASS" mysqldump --single-transaction --no-tablespaces -h "$HOST" -u "$USER" "$DB" | gzip > "$DEST/$NAME-db-$D.sql.gz"
  tar -czf "$DEST/$NAME-files-$D.tar.gz" -C "$(dirname "$SITE")" "$NAME"
  chown "$OWNER": "$DEST" "$DEST/$NAME-db-$D.sql.gz" "$DEST/$NAME-files-$D.tar.gz"
  chmod 600 "$DEST/$NAME-db-$D.sql.gz" "$DEST/$NAME-files-$D.tar.gz"     # contain the site's secrets
  [ "$(gunzip -c "$DEST/$NAME-db-$D.sql.gz" | grep -c 'CREATE TABLE')" -gt 10 ] || die "database backup looks empty"
  ls -lh "$DEST/$NAME-db-$D.sql.gz" "$DEST/$NAME-files-$D.tar.gz"
  echo "Backup date: $D"
else
  D=${2:?backup date, e.g. 2026-11-12-1430 (see ls ~/backups)}
  F="$DEST/$NAME-files-$D.tar.gz"; S="$DEST/$NAME-db-$D.sql.gz"
  [ -s "$F" ] && [ -s "$S" ] || die "backup files for $D not found in $DEST"
  read -r -p "Restore $SITE and database $DB to the backup from $D? Type YES: " ok; [ "$ok" = YES ] || die "cancelled"
  tar -xzf "$F" -C "$(dirname "$SITE")"
  gunzip -c "$S" | MYSQL_PWD="$PASS" mysql -h "$HOST" -u "$USER" "$DB"
  echo "Restored $SITE from $D."
fi
