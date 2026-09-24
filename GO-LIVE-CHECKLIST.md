# Go-live checklist — AffiliateOS

Use with [DEPLOY.md](./DEPLOY.md) for env and queue details. Run in order before opening to customers.

## Product scope (decided — not blockers)

| Item | Decision |
|------|----------|
| Promotion **bulk ESP send** | **Out of scope** — email posts are copy/download only; real sends = campaign sequences + integrations. |
| **Non–D-ID video** | **Out of scope for v1** — UI shows D-ID avatar only. |
| **Mini App** bonus | **Deferred** — wizard chip stays disabled (“Coming soon”). Ebook + mini course only. |

## 1. Infrastructure

- [ ] **Redis** running; `QUEUE_CONNECTION=redis` in production `.env`
- [ ] **Horizon** running (`php artisan horizon`) — all supervisors in `config/horizon.php`
- [ ] **Scheduler** cron: `* * * * * cd /path/to/app && php artisan schedule:run`
- [ ] **`APP_URL`** matches the public HTTPS URL (OAuth and Zernio callbacks)
- [ ] **`php artisan migrate --force`** on deploy
- [ ] **`php artisan config:cache`** / **`route:cache`** in production (after env is final)

## 2. Secrets & integrations

- [ ] `OPENROUTER_API_KEY` — Command Center, campaigns, promotion text/images
- [ ] `ZERNIO_API_KEY` + connect test account in **Settings → Social posting**
- [ ] `ZERNIO_WEBHOOK_SECRET` — WhatsApp/inbox webhooks (required in production)
- [ ] `JVZOO_SECRET_KEY` + product rows seeded for IPN (`products` table)
- [ ] D-ID enabled in `.env` (`services.did` — see `.env.example`) if you advertise video posts
- [ ] `APIFY_API_TOKEN` (optional) — Opportunity Finder / ClickBank live
- [ ] ESP credentials in **Integrations** if leads should sync to Mailchimp, etc.

## 3. Automated quality gate

- [ ] `php artisan test` — full suite should pass before each release

## 4. Smoke tests (manual, ~30 min)

| Step | Pass? |
|------|-------|
| Log in → create/publish **campaign** → public squeeze loads | |
| Opt-in on squeeze → **Campaign lead** + row on **/leads** (campaign filter) | |
| **Bonus stack** `/p/bonus` — CTAs go to **affiliate** link, not bonus viewer | |
| **Promotion Posts**: text post → **ready** → publish to connected X/IG (Zernio) | |
| **Promotion Posts**: carousel or pin → image + caption **ready** | |
| **Command Center**: message Alex → completes on `ai-employee` queue | |
| **Leads → Export CSV** downloads with expected rows | |
| **Email** promotion post → **Copy email** / download `.txt` (no social publish) | |
| **Video** post (optional) → D-ID path only in wizard → generate when D-ID configured | |
| `php artisan schedule:run` — no errors (scheduled posts + campaign emails) | |

## 5. Support — set expectations

Tell support/users:

- Promotion **email** = copy or download; **campaign wizard emails** + **Integrations** handle list sync and follow-ups after opt-in.
- Video = **D-ID avatar** presenter only.
- **Mini App** bonus = not available yet (ebook + mini course).
- Opportunity Finder may be **empty** if Apify/scrape is down — cached trends still work when configured.

## 6. Post-launch monitoring

- Horizon dashboard / failed jobs
- `storage/logs/laravel.log` — Zernio 402, OpenRouter image failures
- JVZoo IPN log for failed provisioning

## 7. Remaining (optional / later — not required for v1 launch)

- **Mini App bonus** — enable wizard + generator (or URL-only MVP).
- **Extra video pipelines** — ElevenLabs, stock, AI b-roll (currently commented out in UI).
- **Promotion → ESP one-shot broadcast** — explicitly declined for v1.
- Catalog **automate** flags (hook scorer, subreddit rules, etc.) — not wired in backend.
- LinkedIn multi-slide carousel — PNG slides via Zernio, not native PDF document upload.
- Tutorial hub — some pages still “content coming soon”.
- Full **WhatsApp Command Center** path — depends on Zernio webhooks + secrets in prod.
