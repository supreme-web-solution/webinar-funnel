# Queue + Horizon Setup

Background work in this app uses Laravel queues. In production you should run **Horizon** (Linux) or **`queue:work`** (local Windows).

## Queues used by this app

| Queue | Jobs | Purpose |
|-------|------|---------|
| `default` | `FetchRedditMentions`, `FetchYouTubeMentions`, `FetchTwitterMentions`, `FetchNewsMentions` | Apify mention discovery (scheduled every 15 min) |
| `traffic-evaluate` | `EvaluateTrafficAutoReplyJob` | Decide if a mention gets an auto-reply |
| `traffic-generate` | `GenerateTrafficAutoReplyJob` | OpenAI reply text |
| `traffic-post` | `PostTrafficAutoReplyJob` | Post reply via Zernio |
| `promotion-generate` | `GeneratePromotionTextJob`, `GeneratePromotionImageJob`, `GeneratePromotionVideoJob`, `PollPromotionVideoJob` | Generate organic promotion assets/content |
| `promotion-publish` | `PublishPromotionPostJob`, `DispatchDuePromotionPostsJob` | Publish now + scheduled promotion dispatch |
| `esp-dispatch` | `DispatchLeadToEspJob` | Send opt-in leads to ESP integrations |
| `webinar-ai` | `IndexFunnelAiSourceJob`, `DispatchWebinarAiReplyJob`, `GenerateWebinarAiReplyJob` | Webinar AI sources + simulated chat replies |

Priority order for workers (highest first):  
`traffic-post` → `traffic-generate` → `traffic-evaluate` → `promotion-publish` → `promotion-generate` → `esp-dispatch` → `webinar-ai` → `default`

## Scheduler (required in production)

Mention fetch jobs are dispatched by the scheduler, not continuously:

```bash
php artisan schedule:run
```

Crontab (every minute):

```cron
* * * * * cd /var/www/dfy-webinar-forge && php artisan schedule:run >> /dev/null 2>&1
```

This runs `mentions:fetch` every 15 minutes and promotion dispatch every minute (`routes/console.php`).

## Local (Windows / Laragon)

Horizon requires `ext-pcntl` and `ext-posix` (not available on Windows CLI). Use a database or redis queue worker:

```bash
php artisan queue:work --queue=traffic-post,traffic-generate,traffic-evaluate,promotion-publish,promotion-generate,esp-dispatch,webinar-ai,default --tries=3 --sleep=1
```

Or use the all-in-one dev script (includes the same queues):

```bash
composer run dev
```

### Live webinar chat (Reverb)

Public webinar chat uses Laravel Reverb. Run alongside the app:

```bash
php artisan reverb:start
```

Ensure `BROADCAST_CONNECTION=reverb` and `VITE_REVERB_*` match your host/port.

## Production (Linux) with Horizon

### 1) Infrastructure

- Redis server running and reachable from the app
- Supervisor (or systemd) to keep Horizon alive
- Cron for `schedule:run`
- Reverb process if using live chat (`php artisan reverb:start` or a second supervisor program)
- `QUEUE_CONNECTION=redis`

### 2) Env

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3) Run Horizon

Horizon is preconfigured in `config/horizon.php` to process all app queues:

```bash
php artisan horizon
```

### 4) Supervisor example

```ini
[program:dfy-horizon]
command=php /var/www/dfy-webinar-forge/artisan horizon
directory=/var/www/dfy-webinar-forge
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/dfy-horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dfy-horizon
```

### 5) Production without Horizon

If you prefer plain workers:

```bash
php artisan queue:work redis --queue=traffic-post,traffic-generate,traffic-evaluate,promotion-publish,promotion-generate,esp-dispatch,webinar-ai,default --tries=3 --sleep=1 --max-time=3600
```

Run under Supervisor with `autorestart=true` (restart after `--max-time`).

## Health checks

- Horizon dashboard: `/horizon` (when enabled)
- Integrations UI: dispatch monitor (queued / failed counts)
- Table: `dispatch_job_logs`
- Failed jobs: `php artisan queue:failed`

If workers are stopped, mention fetches, auto-replies, ESP sync, and webinar AI jobs stay queued until workers resume.
