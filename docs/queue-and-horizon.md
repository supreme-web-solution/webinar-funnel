# Queue + Horizon Setup

This app processes ESP lead sync in background jobs (`DispatchLeadToEspJob`) on the `esp-dispatch` queue.

## Local (Windows / Laragon)

Horizon is not supported on Windows CLI because Laravel Horizon requires `ext-pcntl` and `ext-posix`.

Use regular workers locally:

```bash
php artisan queue:work --queue=esp-dispatch,default --tries=3 --sleep=1
```

Or run all dev services:

```bash
composer run dev
```

## Production (Linux) with Horizon

### 1) Infrastructure

- Redis server running and reachable from app
- Supervisor / systemd to keep Horizon alive
- `QUEUE_CONNECTION=redis`

### 2) Install Horizon (on Linux server)

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate --force
```

### 3) Env

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_QUEUE=default
REDIS_QUEUE_CONNECTION=default
```

### 4) Run Horizon

```bash
php artisan horizon
```

### 5) Supervisor example

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

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dfy-horizon
```

## Queue Health Signals

- App UI: `/integrations` includes dispatch monitor with:
  - queued count
  - failed in last 24h
  - recent dispatch logs
- Logs table: `dispatch_job_logs`

## Notes

- ESP jobs use queue `esp-dispatch`, `tries=3`, `backoff=30`, `timeout=45`.
- If worker/Horizon is down, jobs remain queued and integrations will not dispatch until workers resume.
