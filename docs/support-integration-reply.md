# Reply to the "Remote Support" website brief

Answers from the website side for whoever is setting up `support.pamperins.com`.
Items marked **TO FILL IN** need the owner's answer. No passwords or keys are in this file.

## Part A: what is done on pamperins.com

| Brief item | Status |
|---|---|
| Remote Support page at `https://pamperins.com/remote-support` | **Ready.** Content is in `content/remote-support.html`; paste it into a new article (alias `remote-support`) and add a **Remote Support** main-menu item. |
| "Get Remote Support" button → `https://support.pamperins.com/get-help` | **Done**, opens in a new tab. It uses the site's own button style (bright blue `#2ea8ff`, navy text) instead of the brief's orange inline style, so it matches the theme. |
| Button on every page | **Done another way:** the home page's **Get Remote Support** button, the Remote Support service card and the footer's **Get Remote Support** link go to `/remote-support`, which explains the steps and then links to the support server. The header's **Get Help Now** button goes to the support request form (`/request-support`). |
| Footer link "Remote Support" | **Done** (footer → Contact column). |
| "Client login" → `support.pamperins.com/get-help` | **Not switched yet.** The header's **Customer Login** button still goes to Joomla's own login page. To send it to the support server instead, set *Template Style → Header Buttons → Login Button Link* to `https://support.pamperins.com/get-help`. |
| Ticket queue | **Built on the website for now** (Joomla extension *Support Tickets*): customers create an account, choose the kind of help, fill in a matching form, and follow their tickets at `/my-tickets`. Tickets have `external_system`/`external_id` fields and fire plugin events (`onPticketsAfterCreate`, `onPticketsAfterReply`, `onPticketsAfterUpdate`) so they can be sent to the support server once it has an API. **Wanted from the support server side:** an API endpoint to create a ticket/session request (customer e-mail, type, subject, description, answers as JSON), returning an ID. |

## Part B: answers

1. **Domain and DNS:** **TO FILL IN**: who manages pamperins.com's DNS (GoDaddy, Cloudflare, Namecheap, the web host…)? Can a `support` record be added?
2. **Hosting:** **TO FILL IN**: where the Joomla site runs (shared host / VPS / home server), and whether that is the same network or machine as the support server.
3. **Support server location:** **TO FILL IN**: office/home (public IP, router you can port-forward on) or a rented VPS?
4. **Look:**
   - Colours: navy `#0b1d3a` (header, footer, dark sections), bright blue `#2ea8ff` (buttons, highlights; use navy `#0b1d3a` text on it), raised navy `#12294f`, link blue on white `#0a6fc2`, light background `#f4f7fb`, body text `#142033`.
   - Logo: PT monogram, file `template/media/images/logos/2-pt-monogram.svg` (for a dark background, text already turned into shapes). Browser-tab icon: `template/media/images/favicon.svg`. Source files: `branding/original/`.
   - Font: **Inter** (bold 700–800 for headings, 400 for body text).
   - Joomla template: **pamperins**, a child template of **Cassiopeia**, on Joomla 5.
   - Buttons are fully rounded ("pill" shape), cards have 14px rounded corners.
5. **Contact details:** placeholders for now: phone (920) 555-0100, email help@pamperins.com, hours Mon–Fri 8am–6pm, Sat 9am–1pm, emergency support by appointment. **TO FILL IN** with the real ones (set in *Template Style → Footer Contact Info*).
6. **Pages and wording:** the page address is `/remote-support`. Free diagnostics are mentioned on the page. **TO FILL IN**: prices for remote sessions (the Pricing page shows a placeholder of $45 for the first 30 minutes) and a terms page link if there is one.
7. **Existing customer logins:** the site uses **Joomla's own user accounts** (login, registration and password reset pages are enabled and styled). There is no shop or booking system on the site yet; booking is an external link (**TO FILL IN** which service, e.g. Calendly). These Joomla accounts could be used for single sign-in later.
8. **Email sending:** **TO FILL IN**: does the host provide SMTP, or a service such as Gmail / Google Workspace or Microsoft 365? Joomla needs it for account activation and password reset as well.
