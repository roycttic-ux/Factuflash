#!/usr/bin/env bash
set -Eeuo pipefail
cd "${FACTUFLASH_PROJECT_DIR:-$(dirname "${BASH_SOURCE[0]}")/..}"
archive="${1:?output archive required}"
tar --exclude='*/node_modules' --exclude='*/vendor' --exclude='*/.git' --exclude='*/dist' \
  --exclude='*/.env*' --exclude='*/.phpunit.result.cache' --exclude='api/storage' \
  --exclude='api/bootstrap/cache/*.php' --exclude='api/database/*.sqlite' \
  --exclude='api/public/storage' --exclude='api/public/downloads' --exclude='*.log' \
  --exclude='*.sql' --exclude='*.key' --exclude='*.pem' --exclude='*.crt' \
  -czf "$archive" api frontend ops docker/nginx/conf.d/default.conf docker-compose.yml \
  Dockerfile.nginx Dockerfile.frontend .dockerignore deploy_release.sh
python3 ops/verify_backup.py --release "$archive"
