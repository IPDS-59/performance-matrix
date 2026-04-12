#!/usr/bin/env bash
# deploy.sh — deploy to shared hosting at matriks.bpssulteng.id
# Usage:
#   ./deploy.sh              # full deploy (build + upload + migrate)
#   ./deploy.sh --build-only # only build assets locally
#   ./deploy.sh --upload-only # only upload (skip build)
#   ./deploy.sh --migrate    # only run remote migration/cache commands
#
# Prerequisites (local):
#   - PHP 8.3+, Composer, pnpm
#   - rsync, ssh, scp
#
# Prerequisites (server):
#   - SSH access to the shared hosting account
#   - PHP 8.3+ available as 'php' (or set PHP_BIN below)
#   - The subdomain matriks.bpssulteng.id pointed to $REMOTE_PUBLIC_PATH
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
SSH_USER="${DEPLOY_USER:-}"
SSH_HOST="${DEPLOY_HOST:-}"
SSH_PORT="${DEPLOY_PORT:-22}"
REMOTE_APP_PATH="${DEPLOY_PATH:-}"       # e.g. /home/username/matriks
REMOTE_PUBLIC_PATH="${DEPLOY_PUBLIC:-}"  # e.g. /home/username/public_html/matriks

PHP_BIN="${PHP_BIN:-php}"                # remote PHP binary (may need full path)
COMPOSER_BIN="${COMPOSER_BIN:-composer}" # remote Composer binary

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

info()    { echo -e "${CYAN}[deploy]${NC} $*"; }
success() { echo -e "${GREEN}[ok]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[warn]${NC}  $*"; }
die()     { echo -e "${RED}[error]${NC} $*" >&2; exit 1; }

# ── Parse flags ───────────────────────────────────────────────────────────────
BUILD=true; UPLOAD=true; MIGRATE=true

for arg in "$@"; do
    case "$arg" in
        --build-only)  UPLOAD=false; MIGRATE=false ;;
        --upload-only) BUILD=false;  MIGRATE=false ;;
        --migrate)     BUILD=false;  UPLOAD=false ;;
        --no-build)    BUILD=false ;;
        --no-migrate)  MIGRATE=false ;;
        *) die "Unknown flag: $arg" ;;
    esac
done

# ── Interactive config if not set ─────────────────────────────────────────────
if [ "$UPLOAD" = true ] || [ "$MIGRATE" = true ]; then
    if [ -z "$SSH_USER" ]; then
        read -rp "SSH username (e.g. cpanelusername): " SSH_USER
    fi
    if [ -z "$SSH_HOST" ]; then
        read -rp "SSH host (e.g. server123.hosting.com): " SSH_HOST
    fi
    if [ -z "$REMOTE_APP_PATH" ]; then
        read -rp "Remote app directory (e.g. /home/${SSH_USER}/matriks): " REMOTE_APP_PATH
        REMOTE_APP_PATH="${REMOTE_APP_PATH:-/home/${SSH_USER}/matriks}"
    fi
    if [ -z "$REMOTE_PUBLIC_PATH" ]; then
        read -rp "Remote public_html subdomain dir (e.g. /home/${SSH_USER}/public_html/matriks): " REMOTE_PUBLIC_PATH
        REMOTE_PUBLIC_PATH="${REMOTE_PUBLIC_PATH:-/home/${SSH_USER}/public_html/matriks}"
    fi
fi

SSH_TARGET="${SSH_USER}@${SSH_HOST}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_TARGET}"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 1 — Build frontend assets
# ─────────────────────────────────────────────────────────────────────────────
if [ "$BUILD" = true ]; then
    info "Installing JS dependencies..."
    pnpm install --frozen-lockfile

    info "Building frontend assets (pnpm run build)..."
    pnpm run build
    success "Frontend build complete → public/build/"
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 2 — Install Composer dependencies (production)
# ─────────────────────────────────────────────────────────────────────────────
if [ "$BUILD" = true ]; then
    info "Installing Composer dependencies (no-dev)..."
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    success "Composer install complete."
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3 — Upload files via rsync
# ─────────────────────────────────────────────────────────────────────────────
if [ "$UPLOAD" = true ]; then
    info "Uploading application files to ${SSH_TARGET}:${REMOTE_APP_PATH} ..."

    # Files/dirs to exclude from upload
    EXCLUDES=(
        ".git"
        ".github"
        "node_modules"
        ".env"
        ".env.local"
        ".env.*.local"
        "storage/logs/*"
        "storage/framework/cache/*"
        "storage/framework/sessions/*"
        "storage/framework/views/*"
        "storage/app/public/*"
        "tests"
        "docs"
        "example_static"
        ".beads"
        "deploy.sh"
        "*.md"
        "phpunit.xml"
        "pnpm-lock.yaml"
        "package.json"
        "package-lock.json"
        "tsconfig.json"
        "vite.config.js"
        "tailwind.config.js"
        "postcss.config.js"
        "components.json"
        "resources/js"
        "resources/css"
    )

    EXCLUDE_ARGS=()
    for e in "${EXCLUDES[@]}"; do
        EXCLUDE_ARGS+=("--exclude=$e")
    done

    rsync -avz --delete \
        "${EXCLUDE_ARGS[@]}" \
        --rsync-path="mkdir -p ${REMOTE_APP_PATH} && rsync" \
        -e "ssh -p ${SSH_PORT}" \
        ./ "${SSH_TARGET}:${REMOTE_APP_PATH}/"

    success "Application files uploaded."

    # Create symlink: public_html/matriks → app/public
    info "Setting up public directory symlink..."
    $SSH_CMD bash -s <<REMOTE_SETUP
        set -e
        # Ensure storage directories exist
        mkdir -p "${REMOTE_APP_PATH}/storage/app/public"
        mkdir -p "${REMOTE_APP_PATH}/storage/framework/cache"
        mkdir -p "${REMOTE_APP_PATH}/storage/framework/sessions"
        mkdir -p "${REMOTE_APP_PATH}/storage/framework/views"
        mkdir -p "${REMOTE_APP_PATH}/storage/logs"

        # Correct permissions
        chmod -R 755 "${REMOTE_APP_PATH}/storage"
        chmod -R 755 "${REMOTE_APP_PATH}/bootstrap/cache"

        # If public_html subdir doesn't exist yet, symlink it
        if [ ! -e "${REMOTE_PUBLIC_PATH}" ]; then
            ln -s "${REMOTE_APP_PATH}/public" "${REMOTE_PUBLIC_PATH}"
            echo "Symlink created: ${REMOTE_PUBLIC_PATH} -> ${REMOTE_APP_PATH}/public"
        else
            echo "Public path already exists: ${REMOTE_PUBLIC_PATH}"
        fi
REMOTE_SETUP
    success "Public directory ready."

    # Check .env exists on server, create from example if not
    info "Checking .env on server..."
    $SSH_CMD bash -s <<CHECK_ENV
        if [ ! -f "${REMOTE_APP_PATH}/.env" ]; then
            cp "${REMOTE_APP_PATH}/.env.example" "${REMOTE_APP_PATH}/.env"
            echo "CREATED: ${REMOTE_APP_PATH}/.env from .env.example"
            echo "⚠️  You must edit .env on the server with production values before proceeding."
        else
            echo "OK: .env already exists."
        fi
CHECK_ENV
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 4 — Remote: generate app key, run migrations, cache config
# ─────────────────────────────────────────────────────────────────────────────
if [ "$MIGRATE" = true ]; then
    info "Running remote setup commands..."

    $SSH_CMD bash -s <<REMOTE_ARTISAN
        set -e
        cd "${REMOTE_APP_PATH}"

        echo "→ Checking APP_KEY..."
        if grep -q "APP_KEY=$" .env || grep -q 'APP_KEY=""' .env; then
            ${PHP_BIN} artisan key:generate --force
            echo "  APP_KEY generated."
        else
            echo "  APP_KEY already set."
        fi

        echo "→ Running migrations..."
        ${PHP_BIN} artisan migrate --force

        echo "→ Creating storage symlink..."
        ${PHP_BIN} artisan storage:link --force 2>/dev/null || true

        echo "→ Caching config, routes, views..."
        ${PHP_BIN} artisan config:cache
        ${PHP_BIN} artisan route:cache
        ${PHP_BIN} artisan view:cache

        echo "→ Optimizing..."
        ${PHP_BIN} artisan optimize

        echo "Done."
REMOTE_ARTISAN

    success "Remote setup complete."
fi

# ─────────────────────────────────────────────────────────────────────────────
echo ""
success "Deployment finished! 🎉"
echo ""
echo "  URL: https://matriks.bpssulteng.id"
echo ""
echo "  If first deploy, edit .env on the server:"
echo "    ssh -p ${SSH_PORT} ${SSH_TARGET}"
echo "    nano ${REMOTE_APP_PATH}/.env"
echo ""
echo "  Minimum .env values needed for production:"
cat <<'ENV_HINT'
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://matriks.bpssulteng.id
    APP_KEY=          ← auto-generated above

    DB_CONNECTION=mysql
    DB_HOST=localhost
    DB_PORT=3306
    DB_DATABASE=your_db_name
    DB_USERNAME=your_db_user
    DB_PASSWORD=your_db_password

    SESSION_DRIVER=database
    CACHE_STORE=database
    QUEUE_CONNECTION=database
ENV_HINT
