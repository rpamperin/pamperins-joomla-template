# Deploy to the live server (pamperins.com)

A runbook for Claude Code (or a person) to install or update the site on the live server.
The live server is **Linux, reached over SSH, running Joomla 6.2** (the site was tested on 6.2.0).

What gets installed:

| File (built by `./build.sh`) | What it is |
|---|---|
| `pamperins-template.zip` | Site template: header, home page, styling, login pages |
| `pamperins-support-tickets.zip` | Support Tickets extension: request forms, My Tickets, staff queue |

## Ground rules

- **Back up before changing anything** (step 3), and confirm the backup files exist and aren't empty.
- **Show each server command before running it.** Stop and ask if anything looks different from what this runbook expects.
- **Never ask for passwords in chat.** Use the owner's SSH key / ssh-agent. Read database settings from the server's
  `configuration.php`, and never print the password to the screen or a log.
- **Never run `dev/seed-local.php` on the live server.** It creates demo content for the local test site only.
- Don't use `sudo` for Joomla commands. Run them **as the web server user** (usually `www-data`) so file ownership stays right.
- Installing is an **upgrade**: running it again later keeps settings, logo, modules and tickets.

## Ask the owner first

1. SSH host and user (e.g. `ssh user@pamperins.com`), and whether a non-standard port is used.
2. The Joomla folder on the server (often `/var/www/html`, `/var/www/pamperins.com` or `~/public_html`).
3. Is this the **first install** of these two packages, or an **update**? (`extension:list` in step 2 shows this.)
4. **Test copy first, or straight to the live site?** The owner's plan is a test copy first: do **Part A** below, then
   steps 1–7 against the test copy, then **Part B (go live)**. For later updates, steps 1–7 on the live site are enough.

## Part A. Make a test copy of the live site (first time only)

Goal: an exact copy of the current pamperins.com at a subdomain (e.g. `new.pamperins.com`), with **its own folder and
its own database**, so nothing on the live site changes. Ask the owner which subdomain they added at the registrar.

```bash
SSH="ssh user@pamperins.com"
LIVE=/var/www/pamperins.com          # live Joomla folder (from "Ask the owner first")
STAGE_HOST=new.pamperins.com         # the subdomain the owner added
STAGE=/var/www/$STAGE_HOST           # new folder for the test copy
```

**A1. DNS points at this server.** Both lines must print the same IP address. If the subdomain gives nothing yet,
DNS hasn't spread; wait 15–60 minutes and try again.

```bash
$SSH "curl -s https://api.ipify.org; echo; getent hosts $STAGE_HOST"
```

**A2. Find the web server and the live site's config.** Note whether it's Apache or nginx, and which config file
serves pamperins.com (`LIVE_CONF` below). Don't change anything yet.

```bash
$SSH "systemctl is-active apache2 nginx 2>/dev/null; sudo grep -rlE 'server_name[^;]*pamperins\.com|ServerName[[:space:]]+(www\.)?pamperins\.com' /etc/apache2/sites-enabled /etc/nginx/sites-enabled 2>/dev/null; command -v certbot"
```

**A3. Back up the live site:** run **step 3** below against `$LIVE` first. Part A uses that database backup.

**A4. Copy the site** (files + database) with the tested script. It only reads the live site. It refuses to run if the
test folder already has files in it, or if the test database name is the live one.

```bash
scp deploy/make-test-copy.sh deploy/go-live.sh user@pamperins.com:~/
ssh -t user@pamperins.com "sudo bash ~/make-test-copy.sh $LIVE $STAGE ~/backups/pamperins-db-<date-from-A3>.sql.gz"
```

The `Result` it prints must show `db`/`user` = `pamperins_test`, `robots` = `noindex, nofollow`, `tmp_path`/`log_path`
inside `$STAGE`, and a table count above 0. The new database password is generated on the server and saved only in the
copy's `configuration.php`. If `sudo mysql` asks for a MySQL password, the owner runs this command in their own
terminal, not through this chat.

**A5. Serve the subdomain.** Copy the live site's config file from A2, change only the name and folder, enable it,
test, and reload. **Show the owner the new file before enabling it.**

- **Apache:** copy `LIVE_CONF` to `/etc/apache2/sites-available/$STAGE_HOST.conf`. Set `ServerName $STAGE_HOST`,
  remove `ServerAlias` lines, and set `DocumentRoot`/`<Directory>` to `$STAGE`. Remove any SSL lines (certbot re-adds them).
  Then `sudo a2ensite $STAGE_HOST && sudo apache2ctl configtest && sudo systemctl reload apache2`.
- **nginx:** copy `LIVE_CONF` to `/etc/nginx/sites-available/$STAGE_HOST`. Set `server_name $STAGE_HOST;` and `root $STAGE;`,
  keep the PHP (`fastcgi_pass`) block as is, and remove the `ssl_certificate` lines and the `listen 443` lines.
  Then `sudo ln -s /etc/nginx/sites-available/$STAGE_HOST /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx`.
- **HTTPS:** `sudo certbot --apache -d $STAGE_HOST` (or `--nginx`). If certbot isn't installed, ask the owner how the live
  site's certificate was set up.

**A6. Check the copy:** open `https://$STAGE_HOST/` and `https://$STAGE_HOST/administrator`. Log in with the **same
admin login as the live site**; it's a copy. The pages should look exactly like the current live site.

**A7. Now install the new site on the copy:** do steps **1, 2, 4, 5, 6 and 7** below with `JOOMLA=$STAGE` and
`https://$STAGE_HOST` in place of the live address. Step 3's backup already covers this. The owner and their wife then
review the test copy on phone and computer.

## Part B. Go live (when the owner approves the test copy)

The test copy now has all the settings, menus and pages, so the simplest way to go live is to **make the live site an
exact copy of the test site**:

1. Ask the owner whether anything changed on the **live** site since Part A, such as new user accounts or contact
   messages. Those would be lost; if there are any, note them so they can be re-added.
2. Run **step 3** (backup) on the live site again.
3. Run the go-live script. It shows the newest backup and asks the owner to type `YES`. It copies the test database into
   the live database and the test files into the live folder, **keeping the live `configuration.php`**, clears the cache,
   and lets search engines in again.

```bash
ssh -t user@pamperins.com "sudo bash ~/go-live.sh $LIVE $STAGE"
```

   `-t` gives the command a real terminal for `sudo` and the `YES` prompt. If Claude Code can't answer prompts, the
   owner runs this command in their own terminal.

4. In the **live** admin: System → Clear Cache, and check Global Configuration → Site → Robots isn't *noindex*.
5. Do the **step 7** checks on https://pamperins.com.
6. Keep the test copy for future changes, or remove it: disable the subdomain's site config, then
   `DROP DATABASE pamperins_test`, then delete `$STAGE`.

## 1. Build locally

```bash
git pull
./build.sh
```

Both lines must say `OK: … at zip root`. If `zip`/`php` are missing: `sudo apt install zip unzip php-cli php-xml`.

## 2. Check the server

Set these once (replace the values):

```bash
SSH="ssh user@pamperins.com"
JOOMLA=/var/www/pamperins.com
```

```bash
$SSH "cd $JOOMLA && grep -E 'MAJOR_VERSION =|MINOR_VERSION =' libraries/src/Version.php && php -v | head -1 && stat -c '%U' configuration.php"
```

Expect Joomla **6.x**, PHP **8.3+**, and the owner of `configuration.php` (that's the web server user, `WEBUSER` below).
If Joomla isn't 6.x or 5.x, stop.

```bash
WEBUSER=www-data
$SSH "cd $JOOMLA && sudo -u $WEBUSER php cli/joomla.php extension:list --type=template | grep -i pamperins; sudo -u $WEBUSER php cli/joomla.php extension:list --type=component | grep -i ptickets"
```

No output means first install; otherwise it's an update. (If `sudo -u` isn't allowed and you log in as the web user, drop `sudo -u $WEBUSER`.)

## 3. Back up

```bash
$SSH 'set -e; cd '"$JOOMLA"'; D=$(date +%F-%H%M); mkdir -p ~/backups
DB=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->db;")
HOST=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->host;")
USER=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->user;")
PASS=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->password;")
MYSQL_PWD="$PASS" mysqldump --single-transaction --no-tablespaces -h "$HOST" -u "$USER" "$DB" | gzip > ~/backups/pamperins-db-$D.sql.gz
tar -czf ~/backups/pamperins-files-$D.tar.gz -C "$(dirname '"$JOOMLA"')" "$(basename '"$JOOMLA"')"
ls -lh ~/backups/*$D*'
```

Both files must be listed with a sensible size (DB: KB–MB, files: tens of MB+). If either is tiny or missing, **stop**.

## 4. Upload and install

```bash
scp pamperins-template.zip pamperins-support-tickets.zip user@pamperins.com:/tmp/
$SSH "set -e; cd $JOOMLA
sudo -u $WEBUSER cp /tmp/pamperins-template.zip /tmp/pamperins-support-tickets.zip tmp/
sudo -u $WEBUSER php cli/joomla.php extension:install --path=$JOOMLA/tmp/pamperins-template.zip
sudo -u $WEBUSER php cli/joomla.php extension:install --path=$JOOMLA/tmp/pamperins-support-tickets.zip
rm -f tmp/pamperins-*.zip /tmp/pamperins-*.zip"
```

Each install must print `[OK] Extension installed successfully.` If the CLI install fails with no reason, install the
same zips in the browser instead: Admin → System → Install → Extensions → Upload Package File.

Then check the site still loads:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://pamperins.com/
```

It should print `200`. (The site keeps its current template until step 5 makes Pamperins the default.)

## 5. Admin settings (first install only, in the browser)

Walk the owner through these **one at a time** at `https://pamperins.com/administrator`:

1. **System → Site Template Styles → Pamperins - Default**
   - *Advanced:* Logo = PT monogram (or Custom).
   - *Header Buttons:* Get Help Now → `request-support`. Customer Login → leave as is.
   - *Home Page Hero:* Booking Button Link (Calendly/Google booking page). Remote Support Link → `remote-support`.
   - *Footer Contact Info:* **real** phone, email, service area, hours.
   - Save, then click the **star** to make it the default.
2. **Content → Site Modules → Main Menu:** Position `menu`, Show Title off, Advanced → Layout **Collapsible Dropdown**.
   Unpublish modules that no longer fit, such as the sidebar Login Form.
3. **Remote Support page:** Content → Articles → New, title *Remote Support from Pamperins Tech*, alias `remote-support`.
   Toggle Editor, paste `content/remote-support.html`, and fix the phone/email in it. Then add a Main Menu item **Remote Support**
   (Articles → Single Article) after Services. In the item's **Options** tab, set Category, Author, Published Date, Hits and
   Navigation to *Hide*.
4. **Support ticket pages:** Main Menu → New → *Support Tickets → Support request form*, title **Request Support**,
   alias `request-support`, *Display in Menu* No. Then another item: *Support Tickets → My support tickets*, title **My Tickets**,
   alias `my-tickets`, *Display in Menu* No.
5. **Users → Manage → Options:** Allow User Registration **Yes**, New User Account Activation **Self**.
6. **Components → Support Tickets → Options:** set *Send new-ticket alerts to*, check the remote support link, and **Save**.
7. **Staff accounts:** each technician needs a *Manager* account (Users → New) to work the ticket queue.

On an **update**, skip step 5. Only re-check the template style if new settings were added.

## 6. E-mail

Account sign-up, password reset and all ticket notices need working e-mail.

- System → Global Configuration → **Server → Mail**, then **Send Test Mail**. It must arrive in the owner's inbox (check spam).
- If it fails, set Mailer = **SMTP** with the owner's mail provider (host, port 587 + STARTTLS or 465 + SSL, username/password).
  The owner types the password into the Joomla form themselves.

## 7. Check the live site

On a phone **and** a computer:

- `/` home: logo, hero, service cards, footer details
- `/remote-support`: Get Remote Support button opens `support.pamperins.com/get-help`
- `/request-support`: register a test customer, choose *Remote Computer Help*, submit, and confirm the
  ticket page and both e-mails (staff alert + customer confirmation) arrive
- Admin → Components → Support Tickets: the test ticket appears; reply to it and confirm the customer e-mail
- `/login`, the password reset page, `/contact`, and the pricing page

Afterwards, delete the test ticket and the test customer account.

## Restore the backup (if something goes wrong)

```bash
$SSH 'set -e; cd '"$JOOMLA"'; D=2026-11-09-1430   # <- change to the date in the backup file names (ls ~/backups)
DB=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->db;")
HOST=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->host;")
USER=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->user;")
PASS=$(php -r "include \"configuration.php\"; \$c=new JConfig; echo \$c->password;")
gunzip -c ~/backups/pamperins-db-$D.sql.gz | MYSQL_PWD="$PASS" mysql -h "$HOST" -u "$USER" "$DB"
tar -xzf ~/backups/pamperins-files-$D.tar.gz -C "$(dirname '"$JOOMLA"')"'
```

Restore both the database and the files from the **same** backup date.
