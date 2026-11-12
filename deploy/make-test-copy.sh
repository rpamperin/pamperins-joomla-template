#!/usr/bin/env bash
# Make a test copy of the live Joomla site: separate folder + separate database.
# The live site is only READ, never changed.
#
#   sudo bash make-test-copy.sh <live-folder> <test-folder> <db-backup.sql.gz> [test-db-name]
#   e.g. sudo bash make-test-copy.sh /var/www/pamperins.com /var/www/new.pamperins.com ~/backups/pamperins-db-2026-11-09-1430.sql.gz
#
# MYSQL_ADMIN (default "mysql") is the command that logs into MySQL as an admin; run with sudo on Ubuntu.
# DB_HOST / DB_GRANT_HOST (default localhost): only change if MySQL runs on another machine.
set -euo pipefail

LIVE=${1:?live folder}; STAGE=${2:?test folder}; DUMP=${3:?database backup .sql.gz}; TDB=${4:-pamperins_test}
MYSQL_ADMIN=${MYSQL_ADMIN:-mysql}; DB_HOST=${DB_HOST:-localhost}; DB_GRANT_HOST=${DB_GRANT_HOST:-localhost}
LIVE=${LIVE%/}; STAGE=${STAGE%/}

die() { echo "ERROR: $*" >&2; exit 1; }
cfg() { sed -n "s/^[[:space:]]*public \$$1 = '\(.*\)';.*/\1/p" "$2" | head -1; }

[ -f "$LIVE/configuration.php" ] || die "$LIVE/configuration.php not found"
[ -s "$DUMP" ] || die "backup $DUMP missing or empty"
[ "$LIVE" != "$STAGE" ] || die "test folder must differ from live folder"
[[ "$TDB" =~ ^[A-Za-z0-9_]+$ ]] || die "bad database name"
[ "$TDB" != "$(cfg db "$LIVE/configuration.php")" ] || die "test database name is the live database"
if [ -e "$STAGE" ] && [ -n "$(ls -A "$STAGE" 2>/dev/null)" ]; then die "$STAGE already exists and is not empty"; fi

OWNER=$(stat -c '%U:%G' "$LIVE/configuration.php")
echo "== Copying files $LIVE -> $STAGE"
mkdir -p "$STAGE"
rsync -a --exclude='cache/*' --exclude='administrator/cache/*' --exclude='tmp/*' --exclude='administrator/logs/*' "$LIVE/" "$STAGE/"
chown -R "$OWNER" "$STAGE"

echo "== Creating database $TDB"
PW=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')   # hex only: safe in SQL and PHP
$MYSQL_ADMIN <<SQL
CREATE DATABASE IF NOT EXISTS \`$TDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$TDB'@'$DB_GRANT_HOST' IDENTIFIED BY '$PW';
ALTER USER '$TDB'@'$DB_GRANT_HOST' IDENTIFIED BY '$PW';
GRANT ALL PRIVILEGES ON \`$TDB\`.* TO '$TDB'@'$DB_GRANT_HOST';
FLUSH PRIVILEGES;
SQL
echo "== Loading backup into $TDB"
gunzip -c "$DUMP" | $MYSQL_ADMIN "$TDB"

echo "== Pointing the copy's configuration.php at its own database and folder"
C="$STAGE/configuration.php"
setcfg() { sed -i "s|^\([[:space:]]*public \$$1 = \).*;|\1'$2';|" "$C"; }
sed -i "s|$LIVE|$STAGE|g" "$C"          # tmp_path / log_path
setcfg db "$TDB"; setcfg user "$TDB"; setcfg password "$PW"; setcfg host "$DB_HOST"
setcfg live_site ''; setcfg cookie_domain ''; setcfg robots 'noindex, nofollow'

echo "== Result"
for k in db user host live_site cookie_domain robots tmp_path log_path; do printf '  %-14s %s\n' "$k" "$(cfg $k "$C")"; done
grep -q "$PW" "$C" && echo "  password       (set, not shown)"
echo "  tables in $TDB: $($MYSQL_ADMIN -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TDB'")"
echo "  files: $(du -sh "$STAGE" | cut -f1)  (live: $(du -sh "$LIVE" | cut -f1))"
echo "Done. Live site untouched. Next: web server config for the subdomain (DEPLOY.md A5)."
