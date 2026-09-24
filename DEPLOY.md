# Production runbook (AffiliateOS / dfy-webinar-forge)

## Required processes

1. **Redis** — queue backend (`QUEUE_CONNECTION=redis`).
2. **Horizon** — `php artisan horizon` (supervisors in `config/horizon.php`).
3. **Scheduler** — cron every minute: `php artisan schedule:run`  
   Dispatches scheduled promotion posts, campaign email swipes, mention fetch.

## Queue names (typical worker coverage)

Include at least:

- `promotion-generate` — AI copy, images, carousel slides
- `promotion-publish` — Zernio social publish
- `campaign-generate` — campaign wizard AI build
- `webinar-ai` — Command Center chat, WhatsApp inbound, some funnel AI
- `default` — misc jobs
- `esp-dispatch` — if ESP integrations are used

Example (dev):

```bash
php artisan queue:work --queue=promotion-generate,promotion-publish,campaign-generate,webinar-ai,esp-dispatch,default
```

Production: prefer Horizon over a single `queue:work`.

## Environment (minimum for full product)

| Variable | Purpose |
|----------|---------|
| `OPENROUTER_API_KEY` | Command Center, promotion text/images, campaign AI |
| `ZERNIO_API_KEY` | Social connect, post publish, paid ads API |
| `ZERNIO_WEBHOOK_SECRET` | WhatsApp / inbox webhooks (401 if missing in prod) |
| `APP_URL` | OAuth callbacks (must match public URL) |
| `JVZOO_SECRET_KEY` | IPN verification + user provisioning |
| `APIFY_API_TOKEN` | Mentions, ClickBank live search (optional but recommended) |

See **[GO-LIVE-CHECKLIST.md](./GO-LIVE-CHECKLIST.md)** for a tick-box version of smoke tests and release steps.

## Smoke tests after deploy

1. Publish a campaign → open public squeeze → submit opt-in → lead in DB / `/leads` (filter by **Campaign** when metadata includes `campaign_id`).
2. Promotion Posts: create text post + pin/carousel → status **ready** → publish (Zernio connected).
3. Command Center: create thread post → generation completes on `promotion-generate`.
4. `php artisan schedule:run` once — no errors.

## Leads export

`/leads?export=csv` streams a CSV (respects search, funnel, and campaign filters). Requires an authenticated session.

## Known limitations (document for support)

- Promotion **email** tab: **copy / .txt download** only (no in-app send). Automated follow-ups use **campaign email sequences** after opt-in.
- Video: **D-ID avatar** only in the UI; legacy non–D-ID provider values fail fast if present in old posts.
- Catalog **automate** flags (hook scorer, subreddit rules, etc.): not automated in backend yet.
- LinkedIn “multi-slide carousel”: PNG slides via Zernio, not a native PDF document upload.
- Campaign thank-you **download** uses per-lead `?dl=` token after opt-in (not a static URL on the page JSON).
