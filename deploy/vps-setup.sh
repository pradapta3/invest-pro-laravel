#!/usr/bin/env bash
#
# VPS provisioning script for invest-pro-laravel (Laravel 12 + PHP + MySQL + Nginx).
#
# Target OS : Ubuntu 22.04 / 24.04 LTS (fresh VPS)
# Run as    : root  ->  sudo bash vps-setup.sh
#
# What it does:
#   1. Base packages, timezone, swap file (optional)
#   2. PHP-FPM + required extensions (ondrej/php PPA)
#   3. Composer
#   4. MySQL server + app database/user
#   5. Node.js + npm (for `npm run build` / Vite assets)
#   6. Nginx vhost for Laravel's public/ directory
#   7. Supervisor (queue:work) + cron (schedule:run)
#   8. UFW firewall + fail2ban
#   9. Let's Encrypt SSL via certbot (only if DOMAIN is set)
#  10. Optional: clone REPO_URL and run the first deploy
#
# Configure via environment variables before running, e.g.:
#
#   sudo DOMAIN=invest.example.com \
#        EMAIL=you@example.com \
#        REPO_URL=git@github.com:pradapta3/invest-pro-laravel.git \
#        DB_PASS='change-me' \
#        bash vps-setup.sh
#
# Anything left unset falls back to the defaults below. Re-running the
# script is safe (each step checks for existing state before acting).

set -euo pipefail

# --------------------------------------------------------------------------
# Configuration — override via environment, or edit the defaults below.
# --------------------------------------------------------------------------
DOMAIN="${DOMAIN:-}"                       # e.g. invest.example.com; empty = skip SSL, use server IP
EMAIL="${EMAIL:-admin@example.com}"        # Let's Encrypt notification address
PHP_VERSION="${PHP_VERSION:-8.3}"
APP_NAME="${APP_NAME:-invest-pro-laravel}"
APP_USER="${APP_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/${APP_NAME}}"
REPO_URL="${REPO_URL:-}"                   # git URL to clone; empty = provision server only
GIT_BRANCH="${GIT_BRANCH:-main}"
DB_NAME="${DB_NAME:-db_saham}"
DB_USER="${DB_USER:-idx_invest}"
DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)}"
SETUP_SWAP="${SETUP_SWAP:-true}"
SWAP_SIZE="${SWAP_SIZE:-1G}"
NODE_MAJOR="${NODE_MAJOR:-20}"
TIMEZONE="${TIMEZONE:-Asia/Jakarta}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-256M}"
PHP_UPLOAD_MAX="${PHP_UPLOAD_MAX:-32M}"

CREDS_FILE="/root/${APP_NAME}-credentials.txt"
PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

log() { echo -e "\n\033[1;32m==> $*\033[0m"; }
warn() { echo -e "\033[1;33m!! $*\033[0m"; }

if [[ $EUID -ne 0 ]]; then
  echo "This script must be run as root (sudo bash vps-setup.sh)" >&2
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "This script only supports Debian/Ubuntu (apt-get not found)." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# --------------------------------------------------------------------------
# 1. Base system
# --------------------------------------------------------------------------
setup_base() {
  log "Updating system packages"
  apt-get update -y
  apt-get upgrade -y
  apt-get install -y software-properties-common ca-certificates curl gnupg lsb-release \
    unzip git ufw fail2ban openssl

  log "Setting timezone to ${TIMEZONE}"
  timedatectl set-timezone "${TIMEZONE}" || warn "Could not set timezone, continuing"

  if [[ "${SETUP_SWAP}" == "true" ]] && [[ ! -f /swapfile ]]; then
    log "Creating ${SWAP_SIZE} swap file (helps composer/npm on small VPS)"
    fallocate -l "${SWAP_SIZE}" /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=1024
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
  fi
}

# --------------------------------------------------------------------------
# 2. PHP
# --------------------------------------------------------------------------
setup_php() {
  log "Installing PHP ${PHP_VERSION} and extensions"
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
  apt-get install -y \
    "php${PHP_VERSION}" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" \
    "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-readline" \
    "php${PHP_VERSION}-opcache"

  local ini="/etc/php/${PHP_VERSION}/fpm/php.ini"
  sed -i "s/^memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" "${ini}"
  sed -i "s/^upload_max_filesize = .*/upload_max_filesize = ${PHP_UPLOAD_MAX}/" "${ini}"
  sed -i "s/^post_max_size = .*/post_max_size = ${PHP_UPLOAD_MAX}/" "${ini}"
  sed -i "s/^;date.timezone.*/date.timezone = ${TIMEZONE}/" "${ini}"

  systemctl enable --now "php${PHP_VERSION}-fpm"
}

# --------------------------------------------------------------------------
# 3. Composer
# --------------------------------------------------------------------------
setup_composer() {
  if command -v composer >/dev/null 2>&1; then
    log "Composer already installed, skipping"
    return
  fi
  log "Installing Composer"
  local expected actual
  expected="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL -o /tmp/composer-setup.php https://getcomposer.org/installer
  actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  if [[ "${expected}" != "${actual}" ]]; then
    echo "Composer installer signature mismatch, aborting." >&2
    rm -f /tmp/composer-setup.php
    exit 1
  fi
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
}

# --------------------------------------------------------------------------
# 4. MySQL
# --------------------------------------------------------------------------
setup_mysql() {
  log "Installing MySQL server"
  apt-get install -y mysql-server
  systemctl enable --now mysql

  log "Securing MySQL (remove anonymous users/test db) and creating app database"
  mysql --user=root <<-SQL
    DELETE FROM mysql.user WHERE User='';
    DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
    DROP DATABASE IF EXISTS test;
    DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
    CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
    ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
    GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
    FLUSH PRIVILEGES;
SQL

  {
    echo "MySQL app database : ${DB_NAME}"
    echo "MySQL app user     : ${DB_USER}"
    echo "MySQL app password : ${DB_PASS}"
  } >> "${CREDS_FILE}"
}

# --------------------------------------------------------------------------
# 5. Node.js (for Vite asset build)
# --------------------------------------------------------------------------
setup_node() {
  if command -v node >/dev/null 2>&1; then
    log "Node.js already installed ($(node -v)), skipping"
    return
  fi
  log "Installing Node.js ${NODE_MAJOR}.x"
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
  apt-get install -y nodejs
}

# --------------------------------------------------------------------------
# 6. Nginx
# --------------------------------------------------------------------------
setup_nginx() {
  log "Installing Nginx"
  apt-get install -y nginx

  local server_name="${DOMAIN:-_}"
  local vhost="/etc/nginx/sites-available/${APP_NAME}"

  cat > "${vhost}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${server_name};
    root ${APP_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size ${PHP_UPLOAD_MAX};
}
NGINX

  ln -sf "${vhost}" "/etc/nginx/sites-enabled/${APP_NAME}"
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
  systemctl enable --now nginx
  systemctl reload nginx
}

# --------------------------------------------------------------------------
# 7. Supervisor (queue worker) + cron (scheduler)
# --------------------------------------------------------------------------
setup_supervisor_and_cron() {
  log "Installing Supervisor for the Laravel queue worker"
  apt-get install -y supervisor

  cat > "/etc/supervisor/conf.d/${APP_NAME}-worker.conf" <<SUPERVISOR
[program:${APP_NAME}-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=${APP_DIR}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
user=${APP_USER}
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR

  supervisorctl reread
  supervisorctl update

  log "Installing cron entry for Laravel scheduler (php artisan schedule:run)"
  cat > "/etc/cron.d/${APP_NAME}" <<CRON
* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
CRON
  chmod 644 "/etc/cron.d/${APP_NAME}"
}

# --------------------------------------------------------------------------
# 8. Firewall + fail2ban
# --------------------------------------------------------------------------
setup_firewall() {
  log "Configuring UFW firewall (SSH, HTTP, HTTPS)"
  ufw allow OpenSSH
  ufw allow 'Nginx Full'
  ufw --force enable

  log "Enabling fail2ban with sshd jail"
  systemctl enable --now fail2ban
}

# --------------------------------------------------------------------------
# 9. SSL (only if DOMAIN is set)
# --------------------------------------------------------------------------
setup_ssl() {
  if [[ -z "${DOMAIN}" ]]; then
    warn "DOMAIN not set, skipping Let's Encrypt SSL. Set DOMAIN and re-run to enable HTTPS."
    return
  fi
  log "Requesting Let's Encrypt certificate for ${DOMAIN}"
  apt-get install -y certbot python3-certbot-nginx
  certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
    warn "certbot failed — check DNS for ${DOMAIN} points at this server, then run: certbot --nginx -d ${DOMAIN}"
}

# --------------------------------------------------------------------------
# 10. App user + optional first deploy
# --------------------------------------------------------------------------
setup_app_user() {
  if ! id -u "${APP_USER}" >/dev/null 2>&1; then
    log "Creating deploy user '${APP_USER}'"
    adduser --disabled-password --gecos "" "${APP_USER}"
    usermod -aG www-data "${APP_USER}"
  fi
  mkdir -p "${APP_DIR}"
  chown "${APP_USER}:www-data" "${APP_DIR}"
}

deploy_app() {
  if [[ -z "${REPO_URL}" ]]; then
    warn "REPO_URL not set, skipping application deploy. Server is provisioned; deploy manually or re-run with REPO_URL set."
    return
  fi

  log "Deploying application from ${REPO_URL} (branch ${GIT_BRANCH})"
  if [[ -d "${APP_DIR}/.git" ]]; then
    sudo -u "${APP_USER}" git -C "${APP_DIR}" fetch origin "${GIT_BRANCH}"
    sudo -u "${APP_USER}" git -C "${APP_DIR}" checkout "${GIT_BRANCH}"
    sudo -u "${APP_USER}" git -C "${APP_DIR}" pull origin "${GIT_BRANCH}"
  else
    sudo -u "${APP_USER}" git clone --branch "${GIT_BRANCH}" "${REPO_URL}" "${APP_DIR}"
  fi

  cd "${APP_DIR}"

  sudo -u "${APP_USER}" composer install --no-dev --optimize-autoloader --no-interaction

  if [[ ! -f "${APP_DIR}/.env" ]]; then
    sudo -u "${APP_USER}" cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
  fi

  local app_url="http://${DOMAIN:-$(curl -fsSL ifconfig.me || echo localhost)}"
  [[ -n "${DOMAIN}" ]] && app_url="https://${DOMAIN}"

  sudo -u "${APP_USER}" sed -i \
    -e "s|^APP_ENV=.*|APP_ENV=production|" \
    -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
    -e "s|^APP_URL=.*|APP_URL=${app_url}|" \
    -e "s|^APP_TIMEZONE=.*|APP_TIMEZONE=${TIMEZONE}|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
    "${APP_DIR}/.env"

  sudo -u "${APP_USER}" php artisan key:generate --force

  log "Installing npm dependencies and building frontend assets"
  sudo -u "${APP_USER}" npm ci
  sudo -u "${APP_USER}" npm run build

  log "Running database migrations"
  sudo -u "${APP_USER}" php artisan migrate --force

  sudo -u "${APP_USER}" php artisan storage:link || true

  log "Caching config/routes/views"
  sudo -u "${APP_USER}" php artisan config:cache
  sudo -u "${APP_USER}" php artisan route:cache
  sudo -u "${APP_USER}" php artisan view:cache

  log "Setting file permissions"
  chown -R "${APP_USER}:www-data" "${APP_DIR}"
  find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;
  find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type f -exec chmod 664 {} \;

  supervisorctl restart "${APP_NAME}-worker:*" || true
}

# --------------------------------------------------------------------------
# Main
# --------------------------------------------------------------------------
main() {
  : > "${CREDS_FILE}"
  chmod 600 "${CREDS_FILE}"

  setup_base
  setup_php
  setup_composer
  setup_mysql
  setup_node
  setup_app_user
  setup_nginx
  setup_supervisor_and_cron
  setup_firewall
  setup_ssl
  deploy_app

  log "Done."
  echo "Credentials saved to ${CREDS_FILE} (root-only, chmod 600)."
  echo
  echo "Next steps:"
  echo "  - Point ${DOMAIN:-<your domain>}'s DNS A record at this server's IP if you haven't."
  [[ -z "${DOMAIN}" ]] && echo "  - Re-run with DOMAIN=yourdomain.com to provision HTTPS via Let's Encrypt."
  [[ -z "${REPO_URL}" ]] && echo "  - Re-run with REPO_URL=<git url> (or deploy manually into ${APP_DIR}) to ship the app."
  echo "  - Set TELEGRAM_BOT_TOKEN / TELEGRAM_WEBHOOK_SECRET in .env, then register the webhook with Telegram:"
  echo "      https://${DOMAIN:-<your domain>}/telegram/webhook (requires HTTPS, i.e. DOMAIN must be set)."
  echo "  - Review supervisor worker logs at ${APP_DIR}/storage/logs/worker.log"
}

main "$@"
