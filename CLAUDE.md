# CLAUDE.md

Website for pamperins.com (Pamperins Tech). The live server runs **Joomla 6.2** over SSH; everything is
also tested on Joomla 5.4. Two installable packages:

1. **Site template** `template/`: a **child template of Cassiopeia** (`<parent>cassiopeia</parent>` in
   `template/templateDetails.xml`).
2. **Support Tickets** extension `extensions/com_ptickets/` (Joomla component `com_ptickets`). Customers
   choose one of 7 kinds of help, fill in the matching form, and follow their tickets. Staff work the
   queue in the admin.

See README.md for features. **New server (fresh Joomla): docs/NEW-SERVER.md.** Existing Joomla site / updates: docs/DEPLOY.md.

## Rules

- **Never edit Cassiopeia core files** (`templates/cassiopeia`, `media/templates/site/cassiopeia`).
  This template loads Cassiopeia's CSS/JS and overrides it, so Joomla updates keep working.
- **All styling goes in `template/media/css/user.css`**, including the ticket pages (`.com-ptickets`, `.pt-request…`,
  `.pt-ticket…`). It loads after Cassiopeia's CSS. Colors, spacing and fonts are CSS variables in `:root` at the top of
  that file; re-theme there first. Fonts are self-hosted (Inter in `template/media/fonts/`). Don't add Google Fonts or
  other external calls.
- **Template settings live in `template/templateDetails.xml`**: fieldsets `header` (help/login buttons),
  `home` (hero text and booking/remote-support links), `footer` (phone, email, area, hours) and
  `advanced` (logo picker, sticky header). New fields need language strings in
  `template/language/en-GB/tpl_pamperins.ini` and must be read in `template/index.php`.
- New files or folders must be listed in the manifest's `<files>` / `<media>` sections, or they won't be installed.
- Use Joomla 6-safe APIs: `$this->getDatabase()` (not `getDbo()`), `MailerFactoryInterface` (not `Factory::getMailer()`),
  `$app->getIdentity()` (not `Factory::getUser()`).
- **Servers:** follow docs/NEW-SERVER.md (fresh server) or docs/DEPLOY.md. Back up first, show commands before running them, never ask for passwords
  in chat, and **never run `dev/seed-local.php` on the live site**.

## Layout

- `template/` becomes `pamperins-template.zip`, with `templateDetails.xml` at the zip root.
  - `index.php`: page layout (header, hero, home sections, footer)
  - `script.php`: install script. On first install it copies the logo to `/images/logo.svg` and
    creates the "Home - Service Cards" / "Home - Why Pamperins Tech" modules (positions `pt-services`, `pt-why`).
  - `html/com_users/`: login, registration and password reset overrides
  - `media/images/logos/`: the five selectable logos (text already turned into shapes; see `branding/`)
- `extensions/com_ptickets/` becomes `pamperins-support-tickets.zip` (manifest `ptickets.xml`).
  - `admin/src/Helper/SupportTypes.php`: the 7 request types, statuses and priorities
  - `admin/src/Helper/TicketService.php`: creates tickets and replies, sends e-mails, fires
    `onPticketsAfterCreate` / `onPticketsAfterReply` / `onPticketsAfterUpdate` (for a future RSUP sync)
  - `site/forms/request.xml` (shared questions) + `site/forms/type_<type>.xml` (questions for each type);
    wording in `site/language/en-GB/com_ptickets.ini`
  - To add a request type: add it to `SupportTypes::TYPES`, create `type_<key>.xml`, add its
    `COM_PTICKETS_TYPE_<KEY>` strings, add it to `admin/forms/filter_tickets.xml`, and add a `.pt-card--t-<key>` icon in user.css
- `content/remote-support.html`: Remote Support page, pasted into a Joomla article on the live site
- `docs/DEPLOY.md`: live-server runbook (Part A test copy at a subdomain, steps 1–7 install, Part B go live).
- `docs/NEW-SERVER.md`: fresh Ubuntu 24.04 server → Joomla 6.2 + this site at a subdomain, then switching pamperins.com over.
- `deploy/new-server.sh`: the one-shot installer used by NEW-SERVER.md (Apache, PHP 8.3, MariaDB, verified Joomla download,
  vhost, HTTPS, both packages, then `setup-site.php`). The owner runs it in their own terminal; it asks for the admin
  password on the server. Rehearsed on a blank Ubuntu 24.04 container.
- `deploy/setup-site.php`: creates the pages, menus, contact, ticket pages and settings on a **fresh** site (idempotent).
  `dev/seed-local.php` just calls it with `--local`.
- `deploy/backup.sh`: `sudo bash backup.sh <site-folder>` / `--restore <site-folder> <date>` → the owner's `~/backups`.
- `deploy/make-test-copy.sh` / `deploy/go-live.sh`: run **on the server** (with sudo) by the runbook; tested locally.
  `make-test-copy` only reads the live site; `go-live` asks for `YES` and keeps the live `configuration.php`.
- `docs/support-integration-reply.md`: notes for the support (RSUP) server
- `dev/seed-local.php`: demo content for the **local Docker site only** (test shortcuts: no activation e-mail)
- `screenshots/`: desktop and phone renders from the local test site

## Build

```bash
./build.sh   # -> pamperins-template.zip + pamperins-support-tickets.zip (both gitignored)
```

It needs `zip`/`unzip`. With `php-cli` + `php-xml` installed it also validates every XML file and lints every PHP
file. It fails if a manifest isn't at its zip root.

## Test locally (Docker)

```bash
docker compose up -d   # Joomla 6.2 + MySQL at http://localhost:8080 (admin: /administrator, admin / LocalTestPass123!)
                       # Mailpit (catches all test e-mails) at http://localhost:8025
docker compose cp pamperins-template.zip joomla:/var/www/html/tmp/
docker compose cp pamperins-support-tickets.zip joomla:/var/www/html/tmp/
docker compose exec joomla chown www-data /var/www/html/tmp/pamperins-template.zip /var/www/html/tmp/pamperins-support-tickets.zip
docker compose exec -u www-data joomla php cli/joomla.php extension:install --path=/var/www/html/tmp/pamperins-template.zip
docker compose exec -u www-data joomla php cli/joomla.php extension:install --path=/var/www/html/tmp/pamperins-support-tickets.zip
docker compose exec -u www-data joomla php /project/dev/seed-local.php
docker compose down -v # remove the test site and its database
```

Joomla 5 instead: `JOOMLA_TAG=5-php8.3-apache docker compose up -d`. To send test e-mail to Mailpit, set Global
Configuration → Server → Mail: SMTP, host `mailpit`, port `1025`, no auth, no encryption.

On this machine Docker needs `sudo`. After changing anything, rebuild and run the `cp` / `chown` / `extension:install`
steps again (it installs as an upgrade). Then check, on both desktop and phone widths:
- the home page, a pricing table, the contact form, and the login/register/reset pages
- `/request-support` as a logged-in customer: choose a type, submit, and check the ticket page and e-mails
- Admin → Components → Support Tickets: reply and add an internal note

The repo is mounted read-only in the container at `/project`.
