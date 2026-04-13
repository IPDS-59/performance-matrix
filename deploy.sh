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
# (runs BEFORE loading .env.production so prod DB vars don't bleed into
#  composer's post-autoload-dump hook → php artisan package:discover)
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

# ── Load .env.production to resolve BASE_URL (after build, before zip) ───────
# Loading here avoids prod DB vars leaking into the build step above.
ENV_FILE=".env.production"
APP_URL=""
if [ -f "$ENV_FILE" ]; then
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^[[:space:]]*# ]] && continue
        [[ -z "$key" ]] && continue
        value="${value%%#*}"
        value="${value#\"}" ; value="${value%\"}"
        value="${value#\'}" ; value="${value%\'}"
        # Trim leading/trailing whitespace only (not internal spaces)
        value="${value#"${value%%[! $'\t']*}"}"
        value="${value%"${value##*[! $'\t']}"}"
        export "$key=$value"
    done < "$ENV_FILE"
    # Strip protocol and trailing slash → tes.bpssulteng.id
    BASE_URL="${APP_URL#https://}"
    BASE_URL="${BASE_URL#http://}"
    BASE_URL="${BASE_URL%/}"
    info "Using APP_URL from ${ENV_FILE}: ${APP_URL}"
else
    warn "${ENV_FILE} not found — BASE_URL will be a placeholder in the output."
    BASE_URL="your-domain.com"
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

    # ── Diagnostic script — helps debug server issues without booting Laravel ──
    DIAG_TOKEN=$(openssl rand -hex 16 2>/dev/null \
        || python3 -c "import secrets; print(secrets.token_hex(16))" 2>/dev/null \
        || echo "diagtoken")

    cat > public/diag.php << DIAG_SCRIPT
<?php
// Server diagnostic — DELETE AFTER USE.
// Access: https://yourdomain.com/diag.php?token=YOUR_TOKEN
if ((\$_GET['token'] ?? '') !== '${DIAG_TOKEN}') { http_response_code(403); die('Forbidden'); }

header('Content-Type: text/html; charset=utf-8');
\$root = dirname(__DIR__);
\$checks = [];

// PHP version
\$phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
\$checks[] = ['PHP ' . PHP_VERSION, \$phpOk, \$phpOk ? 'OK' : 'Need >= 8.2'];

// Required extensions
foreach (['pdo', 'pdo_mysql', 'pdo_pgsql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'] as \$ext) {
    \$ok = extension_loaded(\$ext);
    \$checks[] = ["ext: \$ext", \$ok, \$ok ? 'loaded' : 'MISSING'];
}

// Critical paths
foreach ([
    'vendor/autoload.php'      => 'Composer autoloader',
    'bootstrap/app.php'        => 'Bootstrap',
    '.env'                     => '.env file',
    'storage/framework/views'  => 'storage/framework/views (dir)',
    'storage/framework/cache'  => 'storage/framework/cache (dir)',
    'storage/framework/sessions' => 'storage/framework/sessions (dir)',
    'storage/logs'             => 'storage/logs (dir)',
    'bootstrap/cache'          => 'bootstrap/cache (dir)',
] as \$rel => \$label) {
    \$full = \$root . '/' . \$rel;
    \$exists = file_exists(\$full);
    \$writable = \$exists && is_writable(\$full);
    \$checks[] = [\$label, \$exists, \$exists ? (\$writable ? 'exists + writable' : 'exists but NOT writable') : 'MISSING'];
}

// Try loading autoloader
\$autoErr = '';
if (file_exists(\$root . '/vendor/autoload.php')) {
    try {
        ob_start();
        require \$root . '/vendor/autoload.php';
        ob_end_clean();
        \$checks[] = ['autoload require', true, 'OK'];
    } catch (\Throwable \$e) {
        ob_end_clean();
        \$autoErr = \$e->getMessage();
        \$checks[] = ['autoload require', false, \$autoErr];
    }
}

// Try booting Laravel
\$bootErr = '';
\$app = null;
if (empty(\$autoErr) && file_exists(\$root . '/bootstrap/app.php')) {
    try {
        ob_start();
        \$app = require \$root . '/bootstrap/app.php';
        ob_end_clean();
        \$checks[] = ['Laravel bootstrap', true, 'OK'];
    } catch (\Throwable \$e) {
        ob_end_clean();
        \$bootErr = \$e->getMessage();
        \$checks[] = ['Laravel bootstrap', false, htmlspecialchars(\$bootErr)];
    }
}

// Test DB connection via raw PDO (no app booting needed — avoids container issues)
\$envRaw = file_exists(\$root . '/.env') ? file_get_contents(\$root . '/.env') : '';
\$envGet = function(string \$key, string \$default = '') use (\$envRaw): string {
    preg_match('/^' . preg_quote(\$key, '/') . '=(.*)$/m', \$envRaw, \$m);
    return isset(\$m[1]) ? trim(\$m[1], " \t\r\n\"'") : \$default;
};

\$dbConn = \$envGet('DB_CONNECTION', 'mysql');
\$dbHost = \$envGet('DB_HOST', '127.0.0.1');
\$dbPort = \$envGet('DB_PORT', \$dbConn === 'pgsql' ? '5432' : '3306');
\$dbName = \$envGet('DB_DATABASE');
\$dbUser = \$envGet('DB_USERNAME');
\$dbPass = \$envGet('DB_PASSWORD');
\$sessionDriver = \$envGet('SESSION_DRIVER', '?');
\$cacheStore    = \$envGet('CACHE_STORE', '?');

\$checks[] = ['DB_HOST', true, \$dbHost];
\$checks[] = ['DB_CONNECTION', true, \$dbConn];
\$checks[] = ['SESSION_DRIVER', true, \$sessionDriver];
\$checks[] = ['CACHE_STORE', true, \$cacheStore];

try {
    \$dsn = \$dbConn === 'pgsql'
        ? "pgsql:host={\$dbHost};port={\$dbPort};dbname={\$dbName}"
        : "mysql:host={\$dbHost};port={\$dbPort};dbname={\$dbName};charset=utf8mb4";
    \$pdo = new PDO(\$dsn, \$dbUser, \$dbPass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    \$checks[] = ['DB connection', true, "Connected to {\$dbConn}://{\$dbHost}:{\$dbPort}/{\$dbName}"];
} catch (\Throwable \$e) {
    \$checks[] = ['DB connection', false, htmlspecialchars(\$e->getMessage())];
}

echo '<!doctype html><html><head><title>Diagnostics</title>
<style>body{font-family:monospace;padding:2rem;max-width:900px}
table{border-collapse:collapse;width:100%}td,th{padding:.4rem .8rem;border:1px solid #ddd;text-align:left}
.ok{background:#dcfce7}.fail{background:#fee2e2}</style></head><body>
<h2>Server Diagnostics</h2><table><tr><th>Check</th><th>Status</th><th>Detail</th></tr>';
foreach (\$checks as [\$name,\$ok,\$detail]) {
    \$cls = \$ok ? 'ok' : 'fail';
    echo "<tr class='\$cls'><td>\$name</td><td>" . (\$ok?'✓':'✗') . "</td><td>\$detail</td></tr>";
}
echo '</table>';
if (\$bootErr) echo '<h3>Boot error detail</h3><pre style="background:#fee2e2;padding:1rem">' . htmlspecialchars(\$bootErr) . '</pre>';
echo '<p style="color:#888;font-size:.85em">Delete this file after use: public/diag.php</p></body></html>';
DIAG_SCRIPT

    success "diag.php generated (token: ${DIAG_TOKEN})."
fi

# ─────────────────────────────────────────────────────────────────────────────
# STEP 3 — Create the deployment zip
# ─────────────────────────────────────────────────────────────────────────────
if [ "$ZIP" = true ]; then
    info "Creating deployment archive: ${ZIP_NAME} ..."

    EXCLUDES=(
        # VCS / tooling directories
        ".git/*"
        ".github/*"
        ".beads/*"
        ".dolt/*"
        ".claude/*"
        # IDE / editor config (not needed on server)
        ".fleet/*"
        ".idea/*"
        ".nova/*"
        ".vscode/*"
        ".zed/*"
        ".editorconfig"
        ".gitignore"
        ".gitattributes"
        # OS junk
        ".DS_Store"
        "Thumbs.db"
        # Dev dependencies
        "node_modules/*"
        # Test / dev-only files
        "tests/*"
        "docs/*"
        "example_static/*"
        # Dev tooling files
        "phpunit.xml"
        ".phpunit.result.cache"
        ".phpunit.cache/*"
        ".phpactor.json"
        "_ide_helper.php"
        "Homestead.json"
        "Homestead.yaml"
        "auth.json"
        "*.db"
        ".beads-credential-key"
        "storage/pail/*"
        # Env files — .env.example intentionally kept as template
        ".env"
        ".env.prod"
        ".env.production"
        ".env.local"
        ".env.testing"
        ".env.staging"
        ".env.backup"
        # Deploy / CI scripts
        "deploy.sh"
        "migrate-prod.sh"
        "*.md"
        # JS build source (compiled output in public/build/ is kept)
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
        # Runtime-generated storage (dirs are re-injected below as empty)
        "storage/logs/*"
        "storage/framework/cache/*"
        "storage/framework/sessions/*"
        "storage/framework/views/*"
        "storage/app/public/*"
        "matriks-kinerja_*.zip"
    )

    EXCLUDE_ARGS=()
    for e in "${EXCLUDES[@]}"; do
        EXCLUDE_ARGS+=("--exclude=${e}")
    done

    zip -r "${ZIP_PATH}" . "${EXCLUDE_ARGS[@]}" -q
    success "Archive created: ${ZIP_PATH}"

    # Inject .env.production into the zip as .env (never touches the local dev .env)
    if [ -f ".env.production" ]; then
        TMP_ENV_DIR=$(mktemp -d)
        cp .env.production "${TMP_ENV_DIR}/.env"
        (cd "${TMP_ENV_DIR}" && zip "${ZIP_PATH}" .env -q)
        rm -rf "${TMP_ENV_DIR}"
        success ".env.production included in zip as .env"
    else
        warn ".env.production not found — zip will not contain a .env file."
    fi

    # Re-inject required Laravel runtime directories stripped by the excludes above.
    # Without these directories the app 500s immediately on first request.
    info "Ensuring Laravel runtime directories are present in archive..."
    TMP_DIRS=$(mktemp -d)
    mkdir -p \
        "${TMP_DIRS}/storage/framework/cache" \
        "${TMP_DIRS}/storage/framework/sessions" \
        "${TMP_DIRS}/storage/framework/views" \
        "${TMP_DIRS}/storage/logs"
    touch \
        "${TMP_DIRS}/storage/framework/cache/.gitkeep" \
        "${TMP_DIRS}/storage/framework/sessions/.gitkeep" \
        "${TMP_DIRS}/storage/framework/views/.gitkeep" \
        "${TMP_DIRS}/storage/logs/.gitkeep"
    (cd "${TMP_DIRS}" && zip "${ZIP_PATH}" \
        storage/framework/cache/.gitkeep \
        storage/framework/sessions/.gitkeep \
        storage/framework/views/.gitkeep \
        storage/logs/.gitkeep -q)
    rm -rf "${TMP_DIRS}"
    success "Runtime directories injected."

    echo "  Size: $(du -sh "${ZIP_PATH}" | cut -f1)"

    # Remove generated scripts from working directory — they're inside the zip.
    rm -f public/setup.php public/diag.php
    success "Cleaned up public/setup.php and public/diag.php from local directory."
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
echo "  3. Extract into your app directory (e.g. ~/tes/)."
echo "  4. In cPanel → Subdomains (or Addon Domains), set the document root"
echo "     of ${BASE_URL} to point to the public/ subfolder, e.g.:"
echo "       ~/tes/public"
echo "     (Without this, every URL including setup.php will return 404.)"
echo ""
echo "  5. .env is already included (from .env.production). Verify its values:"
cat << ENV_HINT
       APP_ENV=production
       APP_DEBUG=false
       APP_URL=https://${BASE_URL}
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
echo "  6. Set permissions (cPanel File Manager → right-click → Change Permissions):"
echo "       storage/           → (recursive) Read & Write"
echo "       bootstrap/cache/   → (recursive) Read & Write"
echo ""
if [ "$ZIP" = true ]; then
    echo "  7. Run setup (storage link + caches) by visiting:"
    echo ""
    echo -e "     ${GREEN}https://${BASE_URL}/setup.php?token=${SETUP_TOKEN}${NC}"
    echo ""
    echo "     The script self-deletes after a successful run."
fi
