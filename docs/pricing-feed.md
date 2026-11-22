# Pricing feed: office backend (rsup) → website

Prices and plans are kept in one place, the office backend at support.pamperins.com. The website's Pricing page
shows them automatically through the `plg_content_ptpricing` plugin.

## The feed (built on the rsup side)

`GET https://support.pamperins.com/api/public/pricing`

- Public and read-only. Only items marked "show on website". Nothing internal (no cost, margin or client data).
- `Content-Type: application/json`, `Cache-Control: max-age=300`.

```json
{
  "version": 1,
  "updated_at": "2026-11-22T17:00:00Z",
  "currency": "USD",
  "price_note": "Prices plus sales tax.",
  "plans": [
    {
      "id": "basic", "name": "Basic", "price_cents": 2900, "period": "month",
      "price_text": "", "featured": false, "summary": "For one home",
      "features": [
        { "label": "Devices covered", "value": "2" },
        { "label": "Remote support", "value": "Business hours" },
        { "label": "Backup monitoring", "value": false }
      ]
    }
  ],
  "services": [
    { "id": "remote-session", "name": "Remote session", "price_cents": 4500, "unit": "session",
      "price_text": "", "notes": "First 30 minutes" }
  ]
}
```

| Field | Values |
|---|---|
| `version` | `1` (the website ignores feeds with another version) |
| `period` | `month`, `year` or `once` |
| `unit` | `flat`, `hour`, `session`, `device` or `month` |
| feature `value` | text, or `true` (shown as ✓) / `false` (shown as —) |
| `price_text` | when not empty, shown instead of the number ("Call for a quote", "from $45") |
| `featured` | highlights one plan as "Most popular" |
| `price_note` | optional line under the tables (tax, travel) |

Arrays are already in display order. `id`s are stable slugs. Plans are shown side by side in one comparison table;
feature rows are lined up by `label`.

## The website side

- Content plugin `extensions/plg_content_ptpricing/` (`pamperins-pricing-plugin.zip`).
- In the Pricing article, the hand-typed tables sit between `{ptpricing}` and `{/ptpricing}`. While the feed works,
  that block is replaced with the live tables. If the feed has never worked, the hand-typed tables are shown.
- The feed is read at most every 10 minutes (setting). The last good copy is kept in
  `administrator/cache/plg_content_ptpricing/feed.json`, so the page doesn't go blank while rsup is down. A broken or
  wrong-version feed never replaces a good copy. Problems go to `administrator/logs/ptpricing.php`.
- To show an office price change right away: System → Clear Cache (or delete that feed.json).
- Keep the plugin enabled while the tags are in the article; a disabled plugin would show the `{ptpricing}` tags as text.
- `deploy/setup-site.php --pricing-feed` turns this on for an existing site: it enables the plugin and wraps the
  tables of the Pricing article in the tags (it changes nothing else on the page).
- Local testing: the fake provider `dev/test-idp/router.php` also serves a sample feed at `/api/public/pricing`.
