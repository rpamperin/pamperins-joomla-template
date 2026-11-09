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
