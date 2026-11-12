# New server: fresh Joomla 6.2 + the Pamperins site

A runbook for Claude Code (or a person) to put the Pamperins Tech site on a **new Ubuntu 24.04 server** at a
subdomain such as `new.pamperins.com`, starting from nothing. One script does the heavy lifting:

`deploy/new-server.sh` installs Apache, PHP 8.3 and MariaDB, then downloads Joomla 6.2 and checks it is the
official package. It installs Joomla, sets up the website address and HTTPS, installs the Pamperins template and
Support Tickets, and creates the pages and menus. It was rehearsed end to end on a blank Ubuntu 24.04 machine.

> Moving an **existing** Joomla site instead? Use docs/DEPLOY.md. Updating a site that's already set up? DEPLOY.md step 4.

## Ground rules

- **Show each server command before running it.** Stop and ask if anything differs from this runbook.
- **Never ask for passwords in chat.** The script asks for the Joomla admin password **on the server**, in the
  owner's own terminal, where it's hidden as they type. SSH uses the owner's key / ssh-agent.
- The owner runs anything that needs `sudo` or asks questions **in their own terminal** (step 4). Claude Code
  prepares the command and explains the output.
- Don't run `dev/seed-local.php` on a server. It's for the local Docker test site.

## Ask the owner first

1. SSH login for the new server (e.g. `ssh owner@203.0.113.10`), and whether it's **Ubuntu 24.04**.
2. The subdomain for the new site (e.g. `new.pamperins.com`). It needs a DNS **A record** pointing to the new
   server's IP address. The owner says this is already added at the registrar.
3. The e-mail address for the Joomla admin account, HTTPS certificate notices and ticket alerts (e.g. `help@pamperins.com`).
4. The business phone number for the footer and contact page.
5. Does this server already host other websites? (The script adds a new site next to them and doesn't touch others.)

## 1. Build locally

```bash
git pull
./build.sh
```

Both lines must say `OK: … at zip root`.

## 2. Check the server and DNS

```bash
SERVER=owner@203.0.113.10        # from "Ask the owner first"
SITE=new.pamperins.com
ssh $SERVER "grep PRETTY_NAME /etc/os-release; curl -s https://api.ipify.org; echo; getent ahostsv4 $SITE | head -1; ss -ltn | grep -E ':(80|443|3306) ' || echo 'ports 80/443/3306 free'"
```

- It must be **Ubuntu 24.04** (or newer).
- The public IP and the subdomain's address should match. If the subdomain shows nothing, DNS hasn't spread yet.
  The install still works, and HTTPS can be added later (the script prints the command).
- If ports 80/443 are already used by Apache, that's fine: another site is there and ours is added next to it.
  If they're used by **nginx or something else**, stop and ask: the script is written for Apache.
- The hosting company's firewall must allow ports **80 and 443**.

## 3. Upload the setup kit

```bash
ssh $SERVER "mkdir -p ~/pamperins-setup"
scp deploy/new-server.sh deploy/setup-site.php deploy/backup.sh deploy/make-test-copy.sh deploy/go-live.sh content/remote-support.html \
    pamperins-template.zip pamperins-support-tickets.zip $SERVER:~/pamperins-setup/
ssh $SERVER "ls -l ~/pamperins-setup"
```

## 4. Run the installer (the owner, in their own terminal)

Give the owner this command, with their subdomain, e-mail and phone filled in:

```bash
ssh -t owner@203.0.113.10 'sudo PHONE="(920) 555-0100" bash ~/pamperins-setup/new-server.sh new.pamperins.com help@pamperins.com'
```

It takes about 2–5 minutes. It will:

1. Ask for the `sudo` password (normal for the server).
2. Possibly ask the owner to confirm the Joomla download's checksum. This happens only if GitHub couldn't be reached
   for the official one; the owner compares it with the SHA256 on the release page it shows.
3. Ask for a **Joomla admin username** (not `admin`) and **password** (12+ characters, typed twice, hidden).
   **Write these down somewhere safe.** This is the login for `https://<subdomain>/administrator`.

It stops safely, before changing anything else, if the site folder or the `pamperins` database already exists.

Ask the owner to paste the **output** (not passwords) back here. The end of it shows the site, admin address,
folder and database. Expected sections: Server checks → Installing Apache, PHP 8.3, MariaDB → Creating database →
Downloading Joomla (checksum verified) → Installing Joomla → Web server (`answers locally: 200`) → two
`Extension installed successfully` → Creating pages, menus and settings → HTTPS → Done.

If HTTPS was skipped (DNS not ready), run this once the subdomain points to the server:

```bash
ssh -t $SERVER "sudo certbot --apache -d $SITE -m help@pamperins.com --agree-tos --redirect"
```

## 5. E-mail (important)

New customer accounts, password resets and every ticket notice need e-mail. A brand-new server usually **can't
deliver mail by itself** (providers block it, or it lands in spam), so use the owner's real mail service over SMTP.
In `https://<subdomain>/administrator` → **System → Global Configuration → Server → Mail**:

- Mailer: **SMTP**. Host/port/security from the mail provider. Common ones:
  Google Workspace `smtp.gmail.com` / 587 / STARTTLS (with an app password),
  Microsoft 365 `smtp.office365.com` / 587 / STARTTLS.
- SMTP Authentication: Yes. The username and password are typed by the owner into this form, never into the chat.
- From e-mail: an address on that account (e.g. `help@pamperins.com`).
- Save, then **Send Test Mail**. It must arrive (check spam).

## 6. Check it and personalise

On a phone **and** a computer, at `https://<subdomain>`:

- Home (logo, hero, service cards), Services, Remote Support, Pricing, Contact
- **Get Help Now**: register a test customer, confirm the activation e-mail, choose *Remote Computer Help*, submit.
  Check the ticket page and both e-mails (staff alert + customer confirmation).
- Admin → Components → **Support Tickets**: the ticket is there. Reply to it, and check the customer gets the reply e-mail.
- Then delete the test ticket and the test customer.

Personalise in the admin. This is all placeholder text, so walk the owner through it:

- System → Site Template Styles → **Pamperins - Default**: logo choice, footer **hours** and service area, and the hero
  **Booking Button Link** (Calendly/Google booking page). Phone and e-mail were set by the installer.
- Content → Articles: **Pricing** (example prices!), **Services**, Remote Support.
- Content → Site Modules → "Home - Service Cards" / "Home - Why Pamperins Tech": the home page text.
- Users → Manage: a **Manager** account for each technician who will work tickets.

The test site tells search engines not to list it. That's switched off when it becomes the real pamperins.com (below).

## 7. Backups on the new server

After setup, and **before any later change**, the owner runs this in their own terminal:

```bash
ssh -t $SERVER "sudo bash ~/pamperins-setup/backup.sh /var/www/$SITE"
```

It saves the database and files to the owner's `~/backups` (readable only by them, because the files contain the site's
secrets) and prints the **backup date**. To undo a change later, use that date. It asks for `YES` first:

```bash
ssh -t $SERVER "sudo bash ~/pamperins-setup/backup.sh --restore /var/www/$SITE 2026-11-12-1430"
```

Both were tested on the rehearsal server: a deleted page came back after the restore. Copy `~/backups` off the server
now and then (for example `scp -r $SERVER:backups ./server-backups`).

## Later: make it the real pamperins.com

When the owner approves the new site, point the main domain at the new server. **Don't touch the domain's MX
(e-mail) records.** Only the website records change.

1. Back up (step 7).
2. At the registrar: set the **A records** for `pamperins.com` and `www.pamperins.com` to the new server's IP.
   Wait until `getent ahostsv4 pamperins.com` on the server shows its own IP.
3. On the server, add the main domain to the site (both the http and https site files) and get its certificate:
   ```bash
   ssh -t $SERVER "for f in /etc/apache2/sites-available/$SITE.conf /etc/apache2/sites-available/$SITE-le-ssl.conf; do [ -f \$f ] && sudo sed -i '/^\s*ServerName /a\    ServerAlias pamperins.com www.pamperins.com' \$f; done; sudo apache2ctl configtest && sudo systemctl reload apache2 && sudo certbot --apache --expand -d $SITE -d pamperins.com -d www.pamperins.com -m help@pamperins.com --agree-tos --redirect"
   ```
   Show the owner the changed file first (`cat` both files). This step couldn't be rehearsed with real DNS. If certbot
   asks which site to use, pick the one for `$SITE`.
4. Let search engines in: in the admin, System → Global Configuration → Site → Robots = *index, follow*. Save.
5. Check https://pamperins.com and https://www.pamperins.com on phone and computer. Then e-mail links: a
   ticket e-mail's link should open the new site.
6. Keep the old server running a week or two in case something was missed, then retire it.

## Updating the site later

`git pull` and `./build.sh`. Back up (step 7). Then upload the two zips and install them, as in DEPLOY.md **step 4**,
with `JOOMLA=/var/www/<site>` and `WEBUSER=www-data`. Installing again is an upgrade: settings, pages and tickets are kept.
