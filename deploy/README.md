# VPS Setup

`vps-setup.sh` provisions a fresh Ubuntu 22.04/24.04 VPS to run this app: Nginx +
PHP-FPM + MySQL + Composer + Node (Vite build) + Supervisor (queue worker) +
cron (Laravel scheduler) + UFW/fail2ban, and optionally clones the repo and
runs the first deploy.

## Quick start

Provision the server only (no app deploy yet):

```bash
scp deploy/vps-setup.sh root@your-server-ip:/root/
ssh root@your-server-ip
bash vps-setup.sh
```

Provision **and** deploy in one go:

```bash
ssh root@your-server-ip
DOMAIN=invest.example.com \
EMAIL=you@example.com \
REPO_URL=https://github.com/pradapta3/invest-pro-laravel.git \
GIT_BRANCH=main \
DB_PASS='pick-a-strong-password' \
bash vps-setup.sh
```

The script is safe to re-run (e.g. run once without `DOMAIN`/`REPO_URL` to
provision the server, point DNS at the box, then re-run with both set to
finish SSL + deploy).

## What gets configured

| Component | Detail |
|---|---|
| PHP | `PHP_VERSION` (default 8.3) via `ppa:ondrej/php`, with the extensions Laravel/this app needs (mysql, mbstring, xml, curl, zip, bcmath, gd, intl, opcache) |
| MySQL | Local `mysql-server`, anonymous users/test db removed, app database + user created |
| Nginx | Vhost serving `public/`, PHP-FPM via unix socket, HTTPS via Certbot if `DOMAIN` is set |
| Node.js | `NODE_MAJOR` (default 20) for `npm run build` (Vite/Tailwind assets) |
| Supervisor | Runs `php artisan queue:work database` (matches `QUEUE_CONNECTION=database` in `.env.example`) |
| Cron | `* * * * * php artisan schedule:run`, required for the jobs in `routes/console.php` (market data refresh, sentiment, fundamentals) |
| Firewall | UFW allowing SSH + Nginx (80/443), fail2ban for SSH brute force |

## Configuration variables

All optional, set as environment variables before the script (or edit the
defaults at the top of the file):

- `DOMAIN` — your domain; leave empty to skip HTTPS (Telegram webhooks require HTTPS, so set this if the bot uses `/telegram/webhook`)
- `EMAIL` — Let's Encrypt contact address
- `REPO_URL` / `GIT_BRANCH` — leave `REPO_URL` empty to provision the server without deploying
- `APP_USER` (default `deploy`), `APP_DIR` (default `/var/www/invest-pro-laravel`)
- `DB_NAME`, `DB_USER`, `DB_PASS` — random password generated if unset
- `PHP_VERSION`, `NODE_MAJOR`, `TIMEZONE` (default `Asia/Jakarta`, matching `.env.example`)
- `SETUP_SWAP` / `SWAP_SIZE` — adds a swap file, useful on 1GB RAM VPS instances

Generated DB credentials are written to `/root/invest-pro-laravel-credentials.txt`
(root-only, `chmod 600`).

## After the script runs

1. If `REPO_URL` wasn't set, deploy manually into `APP_DIR` (git clone,
   `composer install`, copy `.env`, `php artisan key:generate`, `npm run
   build`, `php artisan migrate --force`).
2. Fill in the remaining secrets in `.env` the script can't know:
   `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `GEMINI_API_KEY`, mail
   settings, etc. Then `php artisan config:cache` again.
3. Register the Telegram webhook (needs HTTPS, so `DOMAIN` must be set):
   `https://<domain>/telegram/webhook`, passing `TELEGRAM_WEBHOOK_SECRET` as
   `secret_token`.
4. Check `supervisorctl status` for the queue worker and
   `storage/logs/worker.log` / `storage/logs/laravel.log` for errors.
5. Future deploys: re-run the script with `REPO_URL`/`GIT_BRANCH` set (it
   pulls, reinstalls deps, rebuilds assets, migrates, and restarts the
   worker), or write a slimmer `deploy.sh` that does just the `deploy_app`
   steps if you don't want the full provisioning pass each time.
