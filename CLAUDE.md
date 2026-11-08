# CLAUDE.md

Pamperins Tech Joomla 5 site template for pamperins.com. It's a **child template of Cassiopeia**
(`<parent>cassiopeia</parent>` in `template/templateDetails.xml`). See README.md for the full
feature list and the live-site install steps.

## Rules

- **Never edit Cassiopeia core files** (`templates/cassiopeia`, `media/templates/site/cassiopeia`).
  This template loads Cassiopeia's CSS/JS and overrides it, so Joomla updates keep working.
- **All styling goes in `template/media/css/user.css`.** It loads after Cassiopeia's CSS. Colors,
  spacing and fonts are CSS variables in `:root` at the top of that file; re-theme there first.
  Fonts are self-hosted (Inter in `template/media/fonts/`). Don't add Google Fonts or other external calls.
- **Settings live in `template/templateDetails.xml`**: fieldsets `header` (help/login buttons),
  `home` (hero text and booking/remote-support links), `footer` (phone, email, area, hours) and
  `advanced` (logo, sticky header). New fields need language strings in
  `template/language/en-GB/tpl_pamperins.ini` and must be read in `template/index.php`.
- New files or folders must be listed in the manifest's `<files>` / `<media>` sections, or they
  won't be installed.

## Layout

- `template/` becomes the zip, with `templateDetails.xml` at the zip root.
  - `index.php`: page layout (header, hero, home sections, footer)
  - `script.php`: install script. On first install it copies the logo to `/images/logo.svg` and
    creates the "Home - Service Cards" / "Home - Why Pamperins Tech" modules (positions
    `pt-services`, `pt-why`).
  - `html/com_users/`: login, registration and password reset overrides
- `dev/seed-local.php`: demo content for the **local Docker site only**. Never run it on the live site.
- `screenshots/`: desktop and phone renders from the local test site

## Build

```bash
./build.sh   # -> pamperins-template.zip (gitignored)
```

It needs `zip`/`unzip`. With `php-cli` + `php-xml` installed it also validates the manifest XML
and lints every PHP file. It fails if `templateDetails.xml` isn't at the zip root.

## Test locally (Docker)

```bash
docker compose up -d   # Joomla 5 + MySQL at http://localhost:8080 (admin: /administrator, admin / LocalTestPass123!)
docker compose cp pamperins-template.zip joomla:/var/www/html/tmp/
docker compose exec joomla chown www-data /var/www/html/tmp/pamperins-template.zip
docker compose exec -u www-data joomla php cli/joomla.php extension:install --path=/var/www/html/tmp/pamperins-template.zip
docker compose exec -u www-data joomla php /project/dev/seed-local.php
docker compose down -v # remove the test site and its database
```

On this machine Docker needs `sudo`. After changing the template, rebuild and run the
`cp` / `chown` / `extension:install` steps again (it installs as an upgrade). Then check the home page,
a pricing table, the contact form and the login/register/reset pages on both desktop and phone widths.
The repo is mounted read-only in the container at `/project`.
