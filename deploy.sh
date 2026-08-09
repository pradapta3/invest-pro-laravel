#!/usr/bin/env bash
#
# Deploy Dompet Ijo to dompetijo.mbayar.my.id.
#
# Run this on the server, from the directory holding docker-compose.yml:
#
#   ./deploy.sh              build + roll out (and seed, on the first deploy)
#   ./deploy.sh --seed       force the seeders to run afterwards
#   ./deploy.sh --no-build   restart with the image that is already built
#
# Safe to re-run: it is the normal way to ship a new commit.
#
set -euo pipefail

cd "$(dirname "$0")"

BOLD=$(printf '\033[1m'); RED=$(printf '\033[31m')
GREEN=$(printf '\033[32m'); YELLOW=$(printf '\033[33m'); OFF=$(printf '\033[0m')

step() { printf '\n%s==> %s%s\n' "$BOLD" "$*" "$OFF"; }
ok()   { printf '%s  ✓ %s%s\n' "$GREEN" "$*" "$OFF"; }
warn() { printf '%s  ! %s%s\n' "$YELLOW" "$*" "$OFF"; }
die()  { printf '\n%s  ✗ %s%s\n\n' "$RED" "$*" "$OFF" >&2; exit 1; }

DO_BUILD=true
FORCE_SEED=false
for arg in "$@"; do
    case "$arg" in
        --no-build) DO_BUILD=false ;;
        --seed)     FORCE_SEED=true ;;
        -h|--help)  sed -n '2,12p' "$0"; exit 0 ;;
        *)          die "unknown option: $arg" ;;
    esac
done

# -----------------------------------------------------------------------------
step "Checking prerequisites"
# -----------------------------------------------------------------------------
command -v docker >/dev/null 2>&1 || die "docker is not installed."
docker compose version >/dev/null 2>&1 || die "the docker compose plugin is missing (install docker-compose-plugin)."
docker info >/dev/null 2>&1 || die "cannot talk to the docker daemon — is it running, and are you root / in the docker group?"
ok "docker $(docker version --format '{{.Server.Version}}') with compose $(docker compose version --short)"

# -----------------------------------------------------------------------------
step "Checking .env"
# -----------------------------------------------------------------------------
if [ ! -f .env ]; then
    [ -f .env.production.example ] || die ".env.production.example is missing — is this the project root?"
    cp .env.production.example .env
    chmod 600 .env
    die ".env has just been created from the production template.
     Edit it now — every <CHANGE_ME> has to be filled in — then run ./deploy.sh again.

     Handy: openssl rand -hex 24    (passwords)
            openssl rand -hex 32    (TELEGRAM_WEBHOOK_SECRET)

     The Telegram and Gemini keys are optional; leave those values empty to
     deploy without them."
fi

# Integrations the app boots fine without. Both fail safe when left empty:
# VerifyTelegramWebhookSecret rejects every request when the secret is blank,
# and AiGenerativeService answers "AI belum dikonfigurasi" instead of calling
# out. What is NOT safe is leaving the literal placeholder in place — the
# template ships in the repo, so a <CHANGE_ME> webhook secret is a secret
# every reader of the repository already knows.
OPTIONAL_PLACEHOLDER_KEYS=" TELEGRAM_BOT_TOKEN TELEGRAM_WEBHOOK_SECRET TELEGRAM_DEFAULT_CHAT_ID TELEGRAM_BROADCAST_CHAT_IDS TELEGRAM_BOT_USERNAME GEMINI_API_KEY "

# Assignment lines only. The template explains <CHANGE_ME> in its own comments,
# and matching those made this check fail on an otherwise correctly filled .env.
REQUIRED_LEFT=""
OPTIONAL_BLANKED=""
while IFS= read -r key; do
    case "$OPTIONAL_PLACEHOLDER_KEYS" in
        *" $key "*)
            sed -i "s#^[[:space:]]*${key}[[:space:]]*=.*#${key}=#" .env
            OPTIONAL_BLANKED="$OPTIONAL_BLANKED $key"
            ;;
        *)
            REQUIRED_LEFT="$REQUIRED_LEFT $key"
            ;;
    esac
done <<EOF
$(grep -oE '^[[:space:]]*[A-Z_][A-Z0-9_]*[[:space:]]*=.*<CHANGE_ME' .env | sed 's/[[:space:]]*=.*//;s/^[[:space:]]*//')
EOF

if [ -n "$REQUIRED_LEFT" ]; then
    printf '\n'
    for key in $REQUIRED_LEFT; do
        grep -nE "^[[:space:]]*${key}[[:space:]]*=" .env | sed 's/^/     /'
    done
    die "the placeholders above are still in .env. Fill them in first."
fi

if [ -n "$OPTIONAL_BLANKED" ]; then
    warn "left unset (still placeholders):$OPTIONAL_BLANKED"
    warn "blanked them so the placeholder text is not used as a real credential."
    warn "Telegram notifications and AI analysis stay off until you fill these in."
fi

# .env holds the database password, the Telegram bot token and the app key.
chmod 600 .env
ok ".env present, required values set, mode 600"

# Read a value out of .env without sourcing it (values may contain spaces).
env_get() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" .env \
        | tail -n1 \
        | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

# APP_KEY encrypts session payloads. `openssl rand -base64 32` produces exactly
# what `artisan key:generate` does, and does not need PHP on the host.
if [ -z "$(env_get APP_KEY)" ]; then
    NEW_KEY="base64:$(openssl rand -base64 32)"
    # '#' as the delimiter because base64 contains '/'; the base64 alphabet has
    # no '#', so the replacement cannot break out of the expression.
    sed -i "s#^APP_KEY=.*#APP_KEY=${NEW_KEY}#" .env
    ok "generated a fresh APP_KEY"
else
    ok "APP_KEY already set"
fi

APP_DOMAIN="$(env_get APP_DOMAIN)"
[ -n "$APP_DOMAIN" ] || die "APP_DOMAIN is not set in .env"

case "$(env_get APP_DEBUG)" in
    true|True|TRUE|1) warn "APP_DEBUG is on — stack traces (including .env values) will be shown to visitors." ;;
esac

case "$(env_get DB_USERNAME)" in
    root) die "DB_USERNAME=root will not work: the mysql image refuses to create root as MYSQL_USER. Pick another name." ;;
esac

# -----------------------------------------------------------------------------
step "Pre-flight: DNS and ports"
# -----------------------------------------------------------------------------
# Caddy can only get a certificate if the name already points here and the
# ACME challenge on :80 can reach it. Warn rather than abort — the stack still
# comes up, it just serves over plain http until DNS propagates.
RESOLVED="$(getent ahostsv4 "$APP_DOMAIN" 2>/dev/null | awk 'NR==1{print $1}' || true)"
PUBLIC_IP="$(curl -fsS --max-time 8 https://api.ipify.org 2>/dev/null || true)"

if [ -z "$RESOLVED" ]; then
    warn "$APP_DOMAIN does not resolve yet. Add an A record pointing at this server before TLS can be issued."
elif [ -n "$PUBLIC_IP" ] && [ "$RESOLVED" != "$PUBLIC_IP" ]; then
    warn "$APP_DOMAIN resolves to $RESOLVED but this host is $PUBLIC_IP — Let's Encrypt validation will fail until that matches."
else
    ok "$APP_DOMAIN resolves to $RESOLVED"
fi

# Something else already on 80/443 (a host nginx, another compose project) is
# the single most common reason the first deploy fails.
if command -v ss >/dev/null 2>&1; then
    for port in 80 443; do
        holder="$(ss -H -ltnp "sport = :$port" 2>/dev/null || true)"
        if [ -n "$holder" ] && ! printf '%s' "$holder" | grep -q 'docker-proxy\|caddy'; then
            warn "port $port is already in use by something that is not this stack:"
            printf '     %s\n' "$holder"
            warn "stop it, or see DEPLOYMENT.md for running behind the existing proxy."
        fi
    done
fi

# True on the very first deploy — used to decide whether to seed.
FIRST_RUN=false
if ! docker volume inspect dompetijo_mysql-data >/dev/null 2>&1; then
    FIRST_RUN=true
    ok "first deployment detected (no database volume yet)"
fi

# -----------------------------------------------------------------------------
if [ "$DO_BUILD" = true ]; then
    step "Building the application image"
    docker compose build --pull
    ok "image built"
fi

# -----------------------------------------------------------------------------
step "Pulling the mysql and caddy images"
# -----------------------------------------------------------------------------
# Pulled separately from `up` so that a slow download is not counted against the
# health-wait timeout below.
docker compose pull --ignore-buildable --quiet
ok "images up to date"

# -----------------------------------------------------------------------------
step "Starting the stack"
# -----------------------------------------------------------------------------
# The app container migrates and warms its caches before nginx starts serving,
# so --wait blocking on the healthcheck means "the new code is actually live".
# The timeout matters: without it a container stuck in a restart loop makes this
# script hang forever instead of showing you the logs.
docker compose up -d --remove-orphans --wait --wait-timeout 300 || {
    printf '\n'
    docker compose ps
    printf '\n--- last 60 lines of app log ---\n'
    docker compose logs --tail=60 app || true
    die "the stack did not come up healthy. Full logs: docker compose logs -f"
}
ok "all services healthy"

# -----------------------------------------------------------------------------
if [ "$FIRST_RUN" = true ] || [ "$FORCE_SEED" = true ]; then
    step "Seeding reference data"
    # Lq45Seeder (ticker list), SubscriptionPlanSeeder (plans) and
    # AdminUserSeeder (the first login). AdminUserSeeder prints a generated
    # password exactly once — copy it out of the output below.
    docker compose exec -T app php artisan db:seed --force --no-interaction
    ok "seeders finished — save the admin password printed above"
fi

# -----------------------------------------------------------------------------
step "Done"
# -----------------------------------------------------------------------------
docker compose ps
cat <<EOF

  Site:      https://${APP_DOMAIN}
  Health:    https://${APP_DOMAIN}/up

  Logs:      docker compose logs -f app
  Scheduler: docker compose logs -f scheduler
  Artisan:   docker compose exec app php artisan <command>

  The first HTTPS request can take a few seconds while Caddy fetches the
  certificate. If it stays on http, check: docker compose logs caddy

EOF
