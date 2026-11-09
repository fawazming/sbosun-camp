#!/usr/bin/env bash
#
# Deploy by pulling in the code root. No Composer, npm or build step.
# A pre-deploy database dump is written to storage/backups/ when possible.
#
set -euo pipefail
cd "$(dirname "$0")"

if php -r 'require "app/bootstrap.php"; exit(db_ready() ? 0 : 1);' >/dev/null 2>&1; then
    mkdir -p storage/backups
    php tools/dump-db.php "storage/backups/pre-deploy-$(date +%Y%m%d-%H%M%S).sql" || true
fi

git pull --ff-only

# Fail fast on syntax errors in the just-pulled code.
find app -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

echo "Deployed. No build step required."
