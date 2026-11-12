#!/usr/bin/env bash
# Fresh Pamperins Tech site on a NEW Ubuntu 24.04 server:
#   Apache + PHP 8.3 + MariaDB -> Joomla 6.2 -> HTTPS -> Pamperins template + Support Tickets -> pages & menus
#
# Upload this folder's files plus the two zips to the server (docs/NEW-SERVER.md), then run IN YOUR OWN TERMINAL:
#   sudo bash new-server.sh <site-address> <your-email> [joomla-version]
#   e.g. sudo bash new-server.sh new.pamperins.com help@pamperins.com
#
# It asks you (on the server, hidden) for the Joomla admin username and password. Nothing secret is printed or
# saved outside Joomla's own configuration.php. It stops if the site folder or database already exists.
set -euo pipefail

SITE=${1:?site address, e.g. new.pamperins.com}
EMAIL=${2:?your e-mail (Joomla admin + HTTPS certificate notices)}
JVER=${3:-6.2.0}
KIT=$(cd "$(dirname "$0")" && pwd)       # folder with this script, setup-site.php, remote-support.html and the zips
DIR=/var/www/$SITE
DB=pamperins; DBUSER=pamperins
PHONE=${PHONE:-"(920) 555-0100"}

say()  { printf '\n\033[1;34m== %s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31mSTOPPED: %s\033[0m\n' "$*" >&2; exit 1; }
svc()  { systemctl enable --now "$1" >/dev/null 2>&1 || service "$1" start >/dev/null; }          # works with or without systemd
restart_svc() { systemctl restart "$1" 2>/dev/null || { service "$1" stop >/dev/null 2>&1 || true; sleep 1; service "$1" start >/dev/null; }; }
reload_apache() { systemctl reload apache2 2>/dev/null || service apache2 reload >/dev/null || apache2ctl graceful; }

# ---------- 0. Checks ----------
[ "$(id -u)" = 0 ] || die "run with sudo"
[[ "$SITE" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "site address looks wrong: $SITE"
[[ "$EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[a-z]{2,}$ ]] || die "e-mail looks wrong: $EMAIL"
[[ "$JVER" =~ ^6\.[0-9]+\.[0-9]+$ ]] || die "Joomla version must look like 6.2.0"
. /etc/os-release
[ "${ID:-}" = ubuntu ] || die "this script is for Ubuntu (found ${ID:-unknown})"
dpkg --compare-versions "${VERSION_ID:-0}" ge 24.04 || die "needs Ubuntu 24.04 or newer (PHP 8.3 for Joomla 6); found $VERSION_ID"
for f in setup-site.php pamperins-template.zip pamperins-support-tickets.zip; do [ -f "$KIT/$f" ] || die "missing $KIT/$f (see docs/NEW-SERVER.md)"; done
[ ! -e "$DIR" ] || [ -z "$(ls -A "$DIR" 2>/dev/null)" ] || die "$DIR already exists and is not empty"
[ ! -e "/etc/apache2/sites-available/$SITE.conf" ] || die "Apache already has a site file for $SITE (/etc/apache2/sites-available/$SITE.conf)"
! grep -rqsE "^[[:space:]]*Server(Name|Alias)[[:space:]].*\b$SITE\b" /etc/apache2/sites-enabled/ || die "another enabled Apache site already answers for $SITE"

say "Server checks"
MYIP=$(curl -fsS -m 10 https://api.ipify.org || true)
DNSIP=$(getent ahostsv4 "$SITE" | awk 'NR==1{print $1}' || true)
echo "  this server's public IP: ${MYIP:-unknown}   $SITE points to: ${DNSIP:-nothing yet}"
DNS_OK=0; [ -n "$MYIP" ] && [ "$MYIP" = "$DNSIP" ] && DNS_OK=1
[ $DNS_OK = 1 ] || echo "  (DNS doesn't point here yet: the site will be installed, and HTTPS is skipped — rerun the certbot line at the end later)"

# ---------- 1. Packages ----------
# Safe on a server that already runs Apache/other sites: this site gets its OWN PHP 8.3 pool (PHP-FPM), so other
# sites keep their PHP version and settings; an existing MySQL/MariaDB or certbot is reused, not replaced.
say "Installing Apache, PHP 8.3, database, certbot (only what's missing)"
export DEBIAN_FRONTEND=noninteractive
PKGS="apache2 php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-intl php8.3-zip php8.3-gd php8.3-curl unzip curl ca-certificates python3"
if command -v mysqld >/dev/null || command -v mariadbd >/dev/null || dpkg -s mysql-server >/dev/null 2>&1 || dpkg -s mariadb-server >/dev/null 2>&1; then
  echo "  database server already installed — using it"; HAVE_DB=1
else
  PKGS="$PKGS mariadb-server"; HAVE_DB=0
fi
if command -v certbot >/dev/null; then
  echo "  certbot already installed — using it"
  certbot plugins 2>/dev/null | grep -q apache || { dpkg -s certbot >/dev/null 2>&1 && PKGS="$PKGS python3-certbot-apache" || echo "  NOTE: this certbot has no Apache plugin; HTTPS step may need: sudo snap install certbot --classic"; }
else
  PKGS="$PKGS certbot python3-certbot-apache"
fi
apt-get update -qq
apt-get install -y -qq $PKGS >/dev/null
php8.3 -v | head -1
a2enmod -q proxy_fcgi setenvif rewrite headers expires >/dev/null

# This site's own PHP pool (settings apply to this site only)
POOL=pamperins-${SITE//./-}
SOCK=/run/php/php8.3-fpm-$POOL.sock
cat > "/etc/php/8.3/fpm/pool.d/$POOL.conf" <<POOLCONF
; PHP for $SITE only (created by new-server.sh)
[$POOL]
user = www-data
group = www-data
listen = $SOCK
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[upload_max_filesize] = 32M
php_admin_value[post_max_size] = 32M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 120
php_admin_flag[output_buffering] = off
POOLCONF
php-fpm8.3 -t 2>&1 | grep -q "test is successful" || die "PHP-FPM config test failed"
[ "$HAVE_DB" = 1 ] || svc mariadb
systemctl enable php8.3-fpm >/dev/null 2>&1 || true
restart_svc php8.3-fpm
for i in $(seq 1 15); do [ -S "$SOCK" ] && break; sleep 1; done
[ -S "$SOCK" ] || die "PHP 8.3 for this site didn't start (no $SOCK) — see /var/log/php8.3-fpm.log"
echo "  PHP 8.3 pool for $SITE is running"
svc apache2
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then ufw allow 'Apache Full' >/dev/null; echo "  firewall: opened web ports"; fi

# ---------- 2. Database ----------
say "Creating database '$DB'"
MYSQL="mysql"
if ! mysql -e "SELECT 1" >/dev/null 2>&1; then
  # Existing MySQL that uses a root password: ask once (hidden), keep it only in a temp file for this run
  read -r -s -p "  MySQL root password (typed here, not shown): " MPW; echo
  MYCNF=$(mktemp); chmod 600 "$MYCNF"; printf '[client]\nuser=root\npassword="%s"\n' "$MPW" > "$MYCNF"; unset MPW
  trap 'rm -rf "${TMPD:-}" "$MYCNF"' EXIT
  MYSQL="mysql --defaults-extra-file=$MYCNF"
  $MYSQL -e "SELECT 1" >/dev/null 2>&1 || die "can't log into MySQL as root"
fi
[ -z "$($MYSQL -N -e "SHOW DATABASES LIKE '$DB'")" ] || die "database '$DB' already exists"
DBPW=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
$MYSQL <<SQL
CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DBUSER'@'localhost' IDENTIFIED BY '$DBPW';
ALTER USER '$DBUSER'@'localhost' IDENTIFIED BY '$DBPW';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBUSER'@'localhost';
CREATE USER IF NOT EXISTS '$DBUSER'@'127.0.0.1' IDENTIFIED BY '$DBPW';
ALTER USER '$DBUSER'@'127.0.0.1' IDENTIFIED BY '$DBPW';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBUSER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
# Which way can the web server reach the database? (socket "localhost" normally; TCP 127.0.0.1 if the socket is locked)
DBHOST=""
for h in localhost 127.0.0.1; do
  if DBPW="$DBPW" sudo -E -u www-data php8.3 -r 'mysqli_report(MYSQLI_REPORT_OFF); $m=@new mysqli($argv[1], $argv[2], getenv("DBPW"), $argv[3]); exit($m->connect_errno ? 1 : 0);' "$h" "$DBUSER" "$DB" 2>/dev/null; then DBHOST=$h; break; fi
done
[ -n "$DBHOST" ] || die "the web server (www-data) can't connect to the database by socket or by 127.0.0.1"
echo "  database + user '$DBUSER' created; web server connects via $DBHOST (password generated, stored only in Joomla's configuration.php)"

# ---------- 3. Joomla download (verified) ----------
say "Downloading Joomla $JVER"
PKG="Joomla_${JVER}-Stable-Full_Package.zip"
TMPD=$(mktemp -d); trap 'rm -rf "$TMPD" "${MYCNF:-}"' EXIT
curl -fsSL -o "$TMPD/$PKG" "https://github.com/joomla/joomla-cms/releases/download/$JVER/$PKG" || die "download failed — check the version at https://www.joomla.org/download.html"
SUM=$(sha256sum "$TMPD/$PKG" | cut -d' ' -f1)
WANT=$(curl -fsS -m 20 "https://api.github.com/repos/joomla/joomla-cms/releases/tags/$JVER" 2>/dev/null \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a.get('digest') or '' for a in d['assets'] if a['name']==sys.argv[1]),'').replace('sha256:',''))" "$PKG" 2>/dev/null || true)
if [ -n "$WANT" ]; then
  [ "$SUM" = "$WANT" ] || die "checksum mismatch: downloaded file is not the official Joomla package"
  echo "  checksum verified against the official GitHub release ($SUM)"
else
  echo "  sha256: $SUM"
  echo "  Couldn't fetch the official checksum automatically. Compare it with the SHA256 for $PKG on"
  echo "  https://github.com/joomla/joomla-cms/releases/tag/$JVER"
  read -r -p "  Does it match? Type yes to continue: " ok; [ "$ok" = yes ] || die "checksum not confirmed"
fi
mkdir -p "$DIR"
unzip -q "$TMPD/$PKG" -d "$DIR"
chown -R www-data:www-data "$DIR"

# ---------- 4. Joomla install ----------
say "Installing Joomla — choose the admin login (typed here on the server, not shown)"
read -r -p "  Admin username (not 'admin'): " AUSER
[[ "$AUSER" =~ ^[A-Za-z0-9._-]{4,}$ ]] && [ "$AUSER" != admin ] || die "username: at least 4 letters/numbers, and not 'admin'"
while :; do
  read -r -s -p "  Admin password (12+ characters): " APW; echo
  read -r -s -p "  Same password again: " APW2; echo
  [ "$APW" = "$APW2" ] && [ ${#APW} -ge 12 ] && break
  echo "  Passwords didn't match or were shorter than 12 characters — try again."
done
PREFIX="pt$(head -c 3 /dev/urandom | od -An -tx1 | tr -d ' \n' | cut -c1-4)_"
set +e
INSTALL_OUT=$(sudo -u www-data php8.3 "$DIR/installation/joomla.php" install -n \
  --site-name="Pamperins Tech" --admin-user="Site Administrator" --admin-username="$AUSER" --admin-password="$APW" \
  --admin-email="$EMAIL" --db-type=mysqli --db-host="$DBHOST" --db-user="$DBUSER" --db-pass="$DBPW" --db-name="$DB" \
  --db-prefix="$PREFIX" --db-encryption=0 2>&1)
RC=$?
set -e
echo "$INSTALL_OUT" | grep -E "OK|ERROR|rror" | tail -4
[ $RC = 0 ] || die "Joomla installer failed (see the lines above). Nothing else was changed; to retry: sudo mysql -e 'DROP DATABASE $DB' and sudo rm -rf $DIR"
unset APW APW2 DBPW
[ -f "$DIR/configuration.php" ] || die "Joomla install did not finish"
rm -rf "$DIR/installation"

C="$DIR/configuration.php"
setcfg() { sed -i "s|^\([[:space:]]*public \$$1 = \).*;|\1$2;|" "$C"; }
setcfg sef_rewrite true
setcfg robots "'noindex, nofollow'"      # test site: keep search engines out (go-live turns this off)
setcfg mailfrom "'$EMAIL'"
setcfg fromname "'Pamperins Tech'"
cp "$DIR/htaccess.txt" "$DIR/.htaccess"
chown www-data:www-data "$DIR/.htaccess" "$C"; chmod 640 "$C"

# ---------- 5. Web server ----------
say "Web server for $SITE"
cat > "/etc/apache2/sites-available/$SITE.conf" <<APACHE
<VirtualHost *:80>
    ServerName $SITE
    DocumentRoot $DIR
    <Directory $DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # PHP 8.3 for this site only (its own PHP-FPM pool)
    <FilesMatch "\.php\$">
        SetHandler "proxy:unix:$SOCK|fcgi://localhost"
    </FilesMatch>
    ErrorLog \${APACHE_LOG_DIR}/$SITE-error.log
    CustomLog \${APACHE_LOG_DIR}/$SITE-access.log combined
</VirtualHost>
APACHE
a2ensite -q "$SITE" >/dev/null
apache2ctl configtest 2>&1 | grep -q "Syntax OK" || die "Apache config test failed: $(apache2ctl configtest 2>&1 | tail -2)"
reload_apache
CODE=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $SITE" http://127.0.0.1/)
echo "  http://$SITE answers locally: $CODE"
[ "$CODE" = 200 ] || die "site doesn't answer (HTTP $CODE) — see /var/log/apache2/$SITE-error.log"

# ---------- 6. Pamperins template + Support Tickets + pages ----------
say "Installing the Pamperins template and Support Tickets"
for z in pamperins-template pamperins-support-tickets; do
  install -o www-data -g www-data -m 644 "$KIT/$z.zip" "$DIR/tmp/$z.zip"
  sudo -u www-data php8.3 "$DIR/cli/joomla.php" extension:install --path="$DIR/tmp/$z.zip" | grep -E "OK|ERROR|rror" || die "install of $z failed"
  rm -f "$DIR/tmp/$z.zip"
done
say "Creating pages, menus and settings"
# Home folders are private on Ubuntu 24.04, so give www-data its own readable copy of the setup files
RUNKIT=$(mktemp -d /tmp/pt-kit.XXXXXX); chmod 755 "$RUNKIT"
install -m 644 "$KIT/setup-site.php" "$RUNKIT/"
[ -f "$KIT/remote-support.html" ] && install -m 644 "$KIT/remote-support.html" "$RUNKIT/"
sudo -u www-data php8.3 "$RUNKIT/setup-site.php" --joomla="$DIR" --email="$EMAIL" --phone="$PHONE" --content="$RUNKIT"
rm -rf "$RUNKIT"

# ---------- 7. HTTPS ----------
if [ $DNS_OK = 1 ]; then
  say "HTTPS certificate (Let's Encrypt)"
  certbot --apache -d "$SITE" -m "$EMAIL" --agree-tos --redirect -n -q && echo "  https://$SITE is secured" \
    || echo "  certbot failed — fix DNS/firewall, then run:  sudo certbot --apache -d $SITE -m $EMAIL --agree-tos --redirect"
else
  echo; echo "  HTTPS skipped (DNS not pointing here yet). Later run:  sudo certbot --apache -d $SITE -m $EMAIL --agree-tos --redirect"
fi

say "Done"
cat <<DONE
  Site:   http://$SITE  (https once the certificate is in place)
  Admin:  http://$SITE/administrator   (user: $AUSER)
  Folder: $DIR      Database: $DB (prefix $PREFIX)
  Apache: /etc/apache2/sites-available/$SITE.conf   PHP: 8.3 pool $POOL (/etc/php/8.3/fpm/pool.d/$POOL.conf)
  Search engines are kept out while this is the test site.
  Next: docs/NEW-SERVER.md step 5 (e-mail) and step 6 (check everything).
DONE
