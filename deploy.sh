#!/usr/bin/env bash
# deploy.sh — build a production zip for manual upload to shared hosting
# Usage:
#   ./deploy.sh              # build assets + composer + zip
#   ./deploy.sh --build-only # only build JS/CSS assets
#   ./deploy.sh --zip-only   # skip build, just repackage
#
# Prerequisites (local):
#   - PHP 8.3+, Composer, pnpm, zip
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Colours ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'

info()    { echo -e "${CYAN}[deploy]${NC} $*"; }
success() { echo -e "${GREEN}[ok]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[warn]${NC}  $*"; }
die()     { echo -e "${RED}[error]${NC} $*" >&2; exit 1; }

# ── Parse flags ───────────────────────────────────────────────────────────────
BUILD=true; ZIP=true

for arg in "$@"; do
    case "$arg" in
        --build-only) ZIP=false ;;
        --zip-only)   BUILD=false ;;
        *) die "Unknown flag: $arg. Use --build-only or --zip-only." ;;
    esac
done

# ── Ensure zip is available ───────────────────────────────────────────────────
command -v zip >/dev/null 2>&1 || die "'zip' is not installed. Install it first."

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
ZIP_NAME="matriks-kinerja_${TIMESTAMP}.zip"
ZIP_PATH="$(pwd)/${ZIP_NAME}"

# ─────────────────────────────────────────────────────────────────────────────
# STEP 1 — Build frontend assets
# ─────────────────────────────────────────────────────────────────────────────
if [ "$BUILD" = true ]; then
    info "Installing JS dependencies..."
    pnpm install --frozen-lockfile

    info "Building frontend assets (pnpm run build)..."
    pnpm run build
    success "Frontend build complete → public/build/"

    info "Installing Composer dependencies (no-dev)..."
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    success "Composer install complete."
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 2 — Create the deployment zip
# ─────────────────────────────────────────────────────────────────────────────
if [ "$ZIP" = true ]; then
    info "Creating deployment archive: ${ZIP_NAME} ..."

    # Patterns to exclude (relative to project root)
    EXCLUDES=(
        ".git/*"
        ".github/*"
        ".beads/*"
        "node_modules/*"
        "tests/*"
        "docs/*"
        "example_static/*"
        ".env"
        ".env.*"
        "deploy.sh"
        "*.md"
        "phpunit.xml"
        "pnpm-lock.yaml"
        "package.json"
        "package-lock.json"
        "tsconfig.json"
        "vite.config.*"
        "tailwind.config.*"
        "postcss.config.*"
        "components.json"
        "resources/js/*"
        "resources/css/*"
        "storage/logs/*"
        "storage/framework/cache/*"
        "storage/framework/sessions/*"
        "storage/framework/views/*"
        "storage/app/public/*"
        "${ZIP_NAME}"
    )

    EXCLUDE_ARGS=()
    for e in "${EXCLUDES[@]}"; do
        EXCLUDE_ARGS+=("--exclude=${e}")
    done

    zip -r "${ZIP_PATH}" . "${EXCLUDE_ARGS[@]}" -q
    success "Archive created: ${ZIP_PATH}"
    echo ""
    echo "  Size: $(du -sh "${ZIP_PATH}" | cut -f1)"
fi

# ─────────────────────────────────────────────────────────────────────────────
echo ""
success "Done!"
echo ""
echo "  Upload ${ZIP_NAME} to your server via cPanel File Manager or FTP."
echo "  Extract it into your app directory (e.g. ~/matriks/)."
echo ""
echo "  After extracting, run these commands via cPanel Terminal or PHP script:"
echo ""
echo "    php artisan key:generate --force   # if .env has no APP_KEY"
echo "    php artisan migrate --force"
echo "    php artisan storage:link --force"
echo "    php artisan config:cache"
echo "    php artisan route:cache"
echo "    php artisan view:cache"
echo "    php artisan optimize"
echo ""
echo "  Minimum .env values needed:"
cat <<'ENV_HINT'
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://matriks.bpssulteng.id
    APP_KEY=          ← generate with: php artisan key:generate --show

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
