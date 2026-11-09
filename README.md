# Pamperins Tech — Joomla 5 template

A child template of Cassiopeia for **pamperins.com** (locally owned IT services, Green Bay & Appleton, WI).

- Navy `#0b1d3a` / bright blue `#2ea8ff` theme, Inter font (self-hosted, no Google calls), mobile-first.
- Header: logo, main menu, **Get Help Now** and **Customer Login** buttons.
- Home: hero with booking + remote-support buttons, five service cards, "Why Pamperins Tech".
- Styled pricing tables, contact page/forms, and branded customer login / registration / password reset.
- Footer: phone, email, service area, hours and copyright, all edited in the template settings.

Cassiopeia itself is never edited. This template reuses Cassiopeia's CSS and JS, so Joomla updates keep working.

## Project layout

```
template/                     -> becomes the zip (templateDetails.xml at the root)
  templateDetails.xml         manifest + settings (header buttons, hero, footer info)
  index.php                   page layout (header, hero, sections, footer)
  script.php                  first install: copies logo to /images/logo.svg, creates the 2 home modules
  html/com_users/...          login, registration and password reset overrides
  language/en-GB/             text strings
  media/css/user.css          ALL styling lives here
  media/fonts/                Inter (SIL Open Font License)
  media/images/               logos/ (5 header logos), favicon.svg (PT square), placeholder logo.svg
build.sh                      -> pamperins-template.zip
docker-compose.yml            local Joomla 5 + MySQL test site
dev/seed-local.php            demo content for the local test site only
branding/                     source logos + text-to-paths.py
screenshots/                  desktop and phone renders from the local test
```

## Build

```bash
./build.sh
```

## Local test site (Docker)

```bash
docker compose up -d
```

Open http://localhost:8080. The admin is at http://localhost:8080/administrator (`admin` / `LocalTestPass123!`, for local use only).

```bash
cp pamperins-template.zip /tmp/ && docker compose cp /tmp/pamperins-template.zip joomla:/var/www/html/tmp/
docker compose exec -u www-data joomla php cli/joomla.php extension:install --path=/var/www/html/tmp/pamperins-template.zip
docker compose exec -u www-data joomla php /project/dev/seed-local.php
```

Remove everything with `docker compose down -v`.

## Install on the live site

1. **Back up** the site first (Akeeba Backup, or your host's backup).
2. Run `./build.sh` (or use a `pamperins-template.zip` you already built).
3. Admin → **System → Install → Extensions → Upload Package File** → drop in `pamperins-template.zip`.
   - This copies the placeholder logo to `/images/logo.svg` if that file doesn't already exist.
   - It also creates two modules, **Home - Service Cards** and **Home - Why Pamperins Tech**, shown on the home page. They use positions that exist only in this template, so the current site doesn't change.
4. **System → Site Template Styles** → click **Pamperins - Default** and fill in the tabs:
   - *Header Buttons*: set **Help Button Link** to your ticket system URL (e.g. `https://support.pamperins.com`) or a menu alias.
   - *Home Page Hero*: set **Booking Button Link** (Calendly / Google booking page) and **Remote Support (RSUP) Link**.
   - *Footer Contact Info*: set your real phone, email, service area and hours.
   - *Advanced → Logo*: pick one of the five Pamperins Tech logos (default: PT monogram), or **Custom** to use your own file.
   - Save, then click the **star** next to Pamperins - Default to make it the site template.
5. **Content → Site Modules** → open your **Main Menu** module → set Position **menu**, turn *Show Title* off, and set *Advanced → Layout* to **Collapsible Dropdown**. Unpublish any modules you don't want (e.g. the sidebar Login Form).
6. Optional menu items: create a **Customer Login** item (*Users → Login Form*) and a **Get Help** item (*Contacts → Single Contact*, alias `get-help`). Both work as hidden items.
7. Check the site on your phone and on a computer, then clear the cache (**System → Clear Cache**).

**Updating later:** build a new zip and upload it again the same way. Your settings, logo and edited home modules are kept.

## Editing content

- **Service cards / Why section**: Content → Site Modules → "Home - Service Cards" / "Home - Why Pamperins Tech".
  Each card is a `<div class="pt-card pt-card--remote">`. The icon comes from the class:
  `--remote`, `--network`, `--security`, `--web`, `--device`.
- **Pricing table**: in an article, use the editor's *Source code* view and give the table `class="pt-pricing"`:
  ```html
  <table class="pt-pricing">
    <thead><tr><th>Plan</th><th>Basic</th><th>Business</th><th>Premium</th></tr></thead>
    <tbody>
      <tr><td>Price</td><td><span class="price">$29<small>/mo</small></span></td><td><span class="price">$79<small>/mo</small></span></td><td><span class="price">$149<small>/mo</small></span></td></tr>
      <tr><td>Devices</td><td>2</td><td>10</td><td>25</td></tr>
    </tbody>
  </table>
  ```
  The middle plan (3rd column) is highlighted as "Most popular". If the table has no label column, use
  `class="pt-pricing pt-pricing--plans-only"`. To highlight specific cells instead, add `class="featured"` to them.
- **Logos**: the five logos are in `template/media/images/logos/` (header versions, no background).
  `branding/original/` has the source files and `branding/outlined/` the same files with the text turned into
  shapes, so they look identical on every device. After editing a source logo, rerun:
  `pip install fonttools brotli uharfbuzz && python3 branding/text-to-paths.py branding/original/*.svg --out branding/outlined`
- **Colors / spacing**: change the variables at the top of `template/media/css/user.css`.
