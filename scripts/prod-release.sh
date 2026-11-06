#!/usr/bin/env bash
# Build, upload and activate a production release, and refuse to ship anything but the zip this run built.
# Why: on 2026-11-06 deploy.sh failed (GitHub 504 during composer install), the glue then picked the newest zip left in the repo
# folder (22 Oct) and extracted that over production. Never choose a zip by "newest file".
# Needs docs/credentials/server.txt (username:/password: lines) and playwright-chromium (see scripts/upload-release.mjs).
set -euo pipefail
cd "$(dirname "$0")/.."

branch=$(git rev-parse --abbrev-ref HEAD)
[ "$branch" = "develop" ] || { echo "Deploy from develop (now on $branch)." >&2; exit 1; }
git fetch -q origin develop
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/develop)" ] || { echo "develop is not at origin/develop. Pull first." >&2; exit 1; }

for attempt in 1 2 3; do
    if ./deploy.sh > /tmp/dep.log 2>&1; then break; fi
    echo "deploy.sh failed (attempt $attempt):" >&2; tail -3 /tmp/dep.log >&2
    [ "$attempt" = 3 ] && { echo "Giving up. Nothing was uploaded." >&2; exit 1; }
    sleep 20
done

# The zip name deploy.sh printed this run, and it must be fresh.
zip=$(grep -o 'matriks-kinerja_[0-9_]*\.zip' /tmp/dep.log | tail -1)
[ -n "$zip" ] && [ -f "$zip" ] || { echo "deploy.sh did not report a zip. Nothing uploaded." >&2; exit 1; }
[ -n "$(find "$zip" -mmin -15)" ] || { echo "$zip is older than 15 minutes. Nothing uploaded." >&2; exit 1; }

mkdir -p /tmp/zipsrv && mv "$zip" /tmp/zipsrv/
U=$(sed -n 's/^username: *//p' docs/credentials/server.txt) P=$(sed -n 's/^password: *//p' docs/credentials/server.txt) ZIP="$zip" node scripts/upload-release.mjs

token=$(grep -o 'setup.php?token=[0-9a-f]*' /tmp/dep.log | head -1)
out=$(curl -s -m 280 "https://kinetik.bpssulteng.id/$token" | sed 's/<[^>]*>//g')
echo "$out" | grep -E "DONE|Error" | head -6
echo "$out" | grep -q "routes .* DONE" || { echo "setup did not finish (no 'routes DONE'). Check the site before trusting this release." >&2; exit 1; }
code=$(curl -s -o /dev/null -w "%{http_code}" https://kinetik.bpssulteng.id/login)
[ "$code" = 200 ] || { echo "/login returned $code" >&2; exit 1; }
echo "Released $zip. /login $code."
