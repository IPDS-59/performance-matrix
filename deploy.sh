#!/usr/bin/env bash
# deploy.sh — build a production zip for manual upload to shared hosting
#
# Usage:
#   ./deploy.sh              # build assets + composer + zip
#   ./deploy.sh --build-only # only build JS/CSS assets
#   ./deploy.sh --zip-only   # skip build, just repackage
#
# Deployment flow (no SSH needed):
#   1. Run new migrations locally:  ./migrate-prod.sh migrate --force
#   2. Build the zip:               ./deploy.sh
#   3. Upload zip via cPanel File Manager or FTP
#   4. Extract into your app directory (e.g. ~/matriks/)
#   5. Create / update .env in that directory
#   6. chmod 775 storage/ bootstrap/cache/ (cPanel → File Manager → right-click)
#   7. Visit the setup URL printed below to finish (storage link + caches)
#
# Prerequisites (local):
#   - PHP 8.3+, Composer, pnpm, zip, openssl
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

# ── Prerequisites ─────────────────────────────────────────────────────────────
command -v zip >/dev/null 2>&1 || die "'zip' is not installed."

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
# STEP 2 — Generate one-time setup.php (written to public/, deleted after zip)
# ─────────────────────────────────────────────────────────────────────────────
if [ "$ZIP" = true ]; then
    SETUP_TOKEN=$(openssl rand -hex 24 2>/dev/null \
        || python3 -c "import secrets; print(secrets.token_hex(24))" 2>/dev/null \
        || die "Cannot generate a random token (need openssl or python3).")

    info "Generating one-time setup script (token: ${SETUP_TOKEN})..."

    cat > public/setup.php << SETUP_SCRIPT
<?php
// One-time deployment setup script — self-deletes after success.
// Access: https://yourdomain.com/setup.php?token=YOUR_TOKEN
declare(strict_types=1);

if ((\$_GET['token'] ?? '') !== '${SETUP_TOKEN}') {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Invalid or missing token.</p>');
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
\$app = require_once __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel \$kernel */
\$kernel = \$app->make(\Illuminate\Contracts\Console\Kernel::class);

\$results = [];
foreach ([
    ['storage:link', ['--force' => true]],
    ['config:cache',  []],
    ['route:cache',   []],
    ['view:cache',    []],
    ['optimize',      []],
] as [\$cmd, \$args]) {
    \$buf  = new \Symfony\Component\Console\Output\BufferedOutput();
    \$code = \$kernel->call(\$cmd, \$args, \$buf);
    \$results[] = [
        'cmd' => 'php artisan ' . \$cmd,
        'ok'  => \$code === 0,
        'out' => htmlspecialchars(\$buf->fetch()),
    ];
}

// Self-delete so the token can't be replayed.
@unlink(__FILE__);

header('Content-Type: text/html; charset=utf-8');
echo <<<HTML
<!doctype html><html lang="id"><head>
<meta charset="utf-8">
<title>Deployment Setup</title>
<style>
  body { font-family: ui-monospace, monospace; padding: 2rem; max-width: 800px; margin: auto; }
  h2   { margin-bottom: 1rem; }
  .ok  { color: #16a34a; }
  .err { color: #dc2626; }
  pre  { background: #f4f4f5; padding: .75rem 1rem; border-radius: 6px; white-space: pre-wrap; }
  .done { margin-top: 2rem; padding: 1rem; background: #dcfce7; border-radius: 6px; color: #166534; font-weight: bold; }
</style>
</head><body>
<h2>Deployment Setup</h2>
HTML;

\$allOk = true;
foreach (\$results as \$r) {
    \$cls = \$r['ok'] ? 'ok' : 'err';
    \$icon = \$r['ok'] ? '✓' : '✗';
    echo "<p class='\$cls'><strong>\$icon \$r[cmd]</strong></p>";
    if (\$r['out'] !== '') echo "<pre>\$r[out]</pre>";
    if (! \$r['ok']) \$allOk = false;
}

if (\$allOk) {
    echo '<div class="done">Setup selesai. File ini telah dihapus otomatis.</div>';
} else {
    echo '<div style="margin-top:2rem;padding:1rem;background:#fee2e2;border-radius:6px;color:#991b1b;font-weight:bold">
          Beberapa perintah gagal — periksa log Laravel di storage/logs/laravel.log.
          File ini telah dihapus otomatis.</div>';
}

echo '</body></html>';
SETUP_SCRIPT

    success "setup.php generated."
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3 — Create the deployment zip
# ─────────────────────────────────────────────────────────────────────────────
if [ "$ZIP" = true ]; then
    info "Creating deployment archive: ${ZIP_NAME} ..."

    EXCLUDES=(
        ".git/*"
        ".github/*"
        ".beads/*"
        ".dolt/*"
        "node_modules/*"
        "tests/*"
        "docs/*"
        "example_static/*"
        ".env"
        ".env.*"
        "deploy.sh"
        "migrate-prod.sh"
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
    echo "  Size: $(du -sh "${ZIP_PATH}" | cut -f1)"

    # Remove setup.php from the working directory — it's inside the zip, not needed locally.
    rm -f public/setup.php
    success "Cleaned up public/setup.php from local directory."
fi

# ─────────────────────────────────────────────────────────────────────────────
echo ""
success "Done!"
echo ""
echo "  Deployment checklist:"
echo ""
echo "  1. (Local) Run new migrations first if any:"
echo "       ./migrate-prod.sh migrate --force"
echo ""
echo "  2. Upload ${ZIP_NAME} via cPanel File Manager or FTP."
echo "  3. Extract into your app directory (e.g. ~/matriks/)."
echo "  4. Create / update .env with at minimum:"
cat <<'ENV_HINT'
       APP_ENV=production
       APP_DEBUG=false
       APP_URL=https://matriks.bpssulteng.id
       APP_KEY=          ← generate locally: php artisan key:generate --show

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
echo ""
echo "  5. Set permissions (cPanel File Manager → right-click → Change Permissions):"
echo "       storage/           → 775"
echo "       bootstrap/cache/   → 775"
echo ""
if [ "$ZIP" = true ]; then
    echo "  6. Run setup (storage link + caches) by visiting:"
    echo ""
    echo -e "     ${GREEN}https://matriks.bpssulteng.id/setup.php?token=${SETUP_TOKEN}${NC}"
    echo ""
    echo "     The script self-deletes after a successful run."
fi
